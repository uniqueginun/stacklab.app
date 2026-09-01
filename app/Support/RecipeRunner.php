<?php

namespace App\Support;

use App\Models\OperationStep;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\SiteCertificate;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use Illuminate\Support\Facades\File;
use RuntimeException;

final readonly class RecipeRunner
{
    public function __construct(
        private SshService $ssh,
    ) {}

    /**
     * @return array{
     *     step_key: string,
     *     success: bool,
     *     changed: bool,
     *     data: array<string, mixed>,
     *     error: array{code: string|null, message: string|null, details: mixed},
     *     output: string
     * }
     */
    public function run(Server $server, OperationStep $step, int $timeout = 600): array
    {
        if (! is_string($server->host_key) || $server->host_key === ''
            || ! is_string($server->host_key_fingerprint) || $server->host_key_fingerprint === '') {
            throw new RuntimeException('The server does not have a confirmed SSH host key.');
        }

        $recipePath = resource_path('recipes/'.$step->recipe.'.sh');
        $libraryPath = resource_path('recipes/_lib.sh');

        if (! File::exists($recipePath)) {
            throw new RuntimeException("Recipe [{$step->recipe}] was not found.");
        }

        $script = File::get($libraryPath)
        .PHP_EOL
        .$this->argumentExports($this->withCertificateMaterials(
            $this->withDatabasePassword(
                is_array($step->arguments) ? $step->arguments : [],
            ),
        ))
        .File::get($recipePath);

        $host = HostFingerprint::fromServer($server);
        $buffer = new ProcessOutputBuffer(function (string $output) use ($step): void {
            $step->persistOutput($output);
        });
        $result = $this->ssh->run(
            $server,
            $host,
            $script,
            $timeout,
            function (string $chunk) use ($buffer): void {
                $buffer->ingest($chunk);
            },
        );
        $buffer->finish();
        $waitOutput = trim($result->output.($result->errorOutput !== '' ? PHP_EOL.$result->errorOutput : ''));
        $combinedOutput = $buffer->output() !== '' ? $buffer->output() : $waitOutput;
        $payload = $this->parsePayload($waitOutput) ?? $this->parsePayload($combinedOutput) ?? $this->invalidPayload($step->recipe, $combinedOutput);

        if (! $result->successful() && $payload['success'] === true) {
            $payload['success'] = false;
            $payload['error']['code'] = $payload['error']['code'] ?? 'ssh_exit';
            $payload['error']['message'] = $payload['error']['message'] ?? 'The remote recipe exited with a non-zero status.';
        }

        $inferred = $this->inferFailureMessage($waitOutput !== '' ? $waitOutput : $combinedOutput);

        if (
            $payload['success'] === false
            && is_string($payload['error']['message'] ?? null)
            && preg_match('/^The recipe exited with status \d+\.$/', $payload['error']['message']) === 1
            && $inferred !== null
        ) {
            $payload['error']['message'] = $inferred;
        }

        return [
            ...$payload,
            'output' => $combinedOutput,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function withDatabasePassword(array $arguments): array
    {
        $databaseId = $arguments['database_id'] ?? null;

        if (! is_int($databaseId) && ! is_numeric($databaseId)) {
            return $arguments;
        }

        $database = ServerDatabase::query()->find((int) $databaseId);

        if ($database === null || ! is_string($database->password) || $database->password === '') {
            return $arguments;
        }

        $arguments['db_password'] = $database->password;

        return $arguments;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function withCertificateMaterials(array $arguments): array
    {
        $certificateId = $arguments['certificate_id'] ?? null;

        if (! is_int($certificateId) && ! is_numeric($certificateId)) {
            return $arguments;
        }

        $certificate = SiteCertificate::query()->find((int) $certificateId);

        if ($certificate === null) {
            return $arguments;
        }

        if (is_string($certificate->certificate) && $certificate->certificate !== '') {
            $arguments['ssl_certificate_b64'] = base64_encode(
                Pem::normalizeCertificates($certificate->certificate) ?? $certificate->certificate,
            );
        }

        if (is_string($certificate->private_key) && $certificate->private_key !== '') {
            $arguments['ssl_private_key_b64'] = base64_encode(
                Pem::normalizePrivateKey($certificate->private_key) ?? $certificate->private_key,
            );
        }

        if (is_string($certificate->chain) && $certificate->chain !== '') {
            $arguments['ssl_chain_b64'] = base64_encode(
                Pem::normalizeCertificates($certificate->chain) ?? $certificate->chain,
            );
        }

        return $arguments;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function argumentExports(array $arguments): string
    {
        $lines = [];

        foreach ($arguments as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $envKey = 'MF_'.strtoupper((string) $key);
            $lines[] = 'export '.$envKey.'='.escapeshellarg((string) $value);
        }

        return $lines === [] ? '' : implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @return array{
     *     step_key: string,
     *     success: bool,
     *     changed: bool,
     *     data: array<string, mixed>,
     *     error: array{code: string|null, message: string|null, details: mixed}
     * }|null
     */
    private function parsePayload(string $output): ?array
    {
        $lines = array_reverse(preg_split('/\R/', trim($output)) ?: []);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (! is_array($decoded) || ! array_key_exists('success', $decoded)) {
                continue;
            }

            return [
                'step_key' => is_string($decoded['step_key'] ?? null) ? $decoded['step_key'] : 'unknown',
                'success' => (bool) $decoded['success'],
                'changed' => (bool) ($decoded['changed'] ?? false),
                'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
                'error' => [
                    'code' => is_string($decoded['error']['code'] ?? null) ? $decoded['error']['code'] : null,
                    'message' => is_string($decoded['error']['message'] ?? null) ? $decoded['error']['message'] : null,
                    'details' => $decoded['error']['details'] ?? null,
                ],
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     step_key: string,
     *     success: bool,
     *     changed: bool,
     *     data: array<string, mixed>,
     *     error: array{code: string|null, message: string|null, details: mixed}
     * }
     */
    private function invalidPayload(string $recipe, string $combinedOutput): array
    {
        return [
            'step_key' => $recipe,
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => [
                'code' => 'invalid_recipe_output',
                'message' => $this->inferFailureMessage($combinedOutput) ?? 'The recipe did not return a valid JSON result payload.',
                'details' => $combinedOutput !== '' ? mb_substr($combinedOutput, 0, 2000) : null,
            ],
        ];
    }

    private function inferFailureMessage(string $output): ?string
    {
        $fatal = null;

        foreach (array_reverse(preg_split('/\R/', $output) ?: []) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'ssh:')) {
                return $line;
            }

            if (str_contains($line, 'does not have a Release file') || str_starts_with($line, 'E: ')) {
                return $line;
            }

            if (str_starts_with($line, 'npm ERR!')) {
                if (
                    str_contains($line, 'A complete log')
                    || str_contains($line, '_logs/')
                    || str_contains($line, 'npm help config')
                    || $line === 'npm ERR! network'
                ) {
                    continue;
                }

                if (str_contains($line, 'code ') || str_contains($line, 'ECONNRESET') || str_contains($line, 'ENOTFOUND') || str_contains($line, 'ETIMEDOUT')) {
                    return $line;
                }

                $fatal ??= $line;

                continue;
            }

            if ($fatal === null && str_starts_with($line, 'fatal:')) {
                $fatal = $line;
            }
        }

        return $fatal;
    }
}
