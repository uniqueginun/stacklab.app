<?php

namespace App\Ssh;

use App\Models\Server;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class SshService
{
    public function __construct(
        private KeypairParser $keypairParser,
        private HostkeyParser $hostkeyParser,
    ) {}

    /**
     * @return array{
     *     host_key_fingerprint: string,
     *     known_hosts_path: string,
     *     ssh_public_key: string,
     *     ssh_private_key: string,
     * }
     */
    public function generateKeyPair(Server $server): array
    {
        $keyName = Str::of($server->name)->slug()->prepend("id_ed25519_{$server->id}_")->toString();

        $scriptPath = resource_path('recipes/ssh');

        $result = Process::path($scriptPath)->timeout(120)->run([
            'sudo',
            './setup_ssh_key.sh',
            'www-data',
            $server->host,
            $keyName,
            (string) $server->ssh_port,
        ]);

        $attributes = $this->keypairParser->parseKeyOutput(
            output: $result->output(),
            error: $result->errorOutput(),
            exit_code: $result->exitCode() ?? 1,
            success: $result->successful(),
        );

        $attributes['ssh_private_key'] = $this->readPrivateKeyMaterial($attributes['ssh_private_key']);

        return $attributes;
    }

    public function discoverHost(Server $server): HostFingerprint
    {
        $result = Process::timeout(15)->run([
            'ssh-keyscan',
            '-T', '10',
            '-p', (string) $server->ssh_port,
            $server->host,
        ]);

        $hostFingerprint = $this->hostkeyParser->parse($result->output());

        if ($hostFingerprint === null) {
            throw new RuntimeException('The SSH service did not return a supported host key.');
        }

        return $hostFingerprint;
    }

    /**
     * @return array{os: string, os_version: string}|array{}
     */
    public function verifyConnection(Server $server, HostFingerprint $host): array
    {
        return $this->withRuntimeFiles($server, $host, function (string $privateKeyPath, string $knownHostsPath) use ($server): array {
            try {
                $result = Process::timeout(20)->run([
                    ...$this->sshBase($server, $privateKeyPath, $knownHostsPath),
                    '--',
                    '. /etc/os-release && printf "mini-forge-ok os=%s version=%s" "$ID" "$VERSION_ID" && sudo -n true',
                ])->throw();
            } catch (ProcessFailedException $e) {
                $error = trim($e->result->errorOutput());

                throw new RuntimeException(
                    filled($error) ? $error : 'SSH connection verification failed.',
                    previous: $e,
                );
            }

            return $this->parseOsProbe($result->output());
        });
    }

    /**
     * @return array{os: string, os_version: string}|array{}
     */
    private function parseOsProbe(string $output): array
    {
        if (preg_match('/mini-forge-ok\s+os=([a-z0-9]+)\s+version=([0-9.]+)/i', $output, $matches) !== 1) {
            return [];
        }

        return [
            'os' => strtolower($matches[1]),
            'os_version' => $matches[2],
        ];
    }

    public function run(
        Server $server,
        HostFingerprint $host,
        string $script,
        int $timeout = 600,
        ?callable $onOutput = null,
    ): SshResult {
        return $this->withRuntimeFiles($server, $host, function (string $privateKeyPath, string $knownHostsPath) use ($server, $script, $timeout, $onOutput): SshResult {
            $pending = Process::timeout($timeout)->input($script);
            $command = [
                ...$this->sshBase($server, $privateKeyPath, $knownHostsPath),
                '--',
                'bash', '-s',
            ];

            if ($onOutput === null) {
                $result = $pending->run($command);

                return new SshResult($result->exitCode() ?? 1, $result->output(), $result->errorOutput());
            }

            $process = $pending->start($command, function (string $type, string $chunk) use ($onOutput): void {
                $onOutput($chunk);
            });
            $result = $process->wait();

            return new SshResult($result->exitCode() ?? 1, $result->output(), $result->errorOutput());
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(string, string): TReturn  $callback
     * @return TReturn
     */
    private function withRuntimeFiles(Server $server, HostFingerprint $host, callable $callback): mixed
    {
        $directory = storage_path('app/private/ssh-runtime/'.Str::uuid());
        $privateKeyPath = $directory.'/management-key';
        $knownHostsPath = $directory.'/known-hosts';

        File::ensureDirectoryExists($directory, 0700);

        try {
            File::put($privateKeyPath, $this->readPrivateKeyMaterial((string) $server->ssh_private_key));
            File::put($knownHostsPath, $this->knownHostName($server).' '.$host->key.PHP_EOL);
            $this->restrictPrivateKey($privateKeyPath);

            return $callback($privateKeyPath, $knownHostsPath);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @return list<string>
     */
    private function sshBase(Server $server, string $privateKeyPath, string $knownHostsPath): array
    {
        return [
            'ssh',
            '-i', $privateKeyPath,
            '-o', 'IdentitiesOnly=yes',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile='.$knownHostsPath,
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            '-p', (string) $server->ssh_port,
            $server->ssh_user.'@'.$server->host,
        ];
    }

    private function knownHostName(Server $server): string
    {
        $port = (int) $server->ssh_port;

        return $port === 22 ? $server->host : '['.$server->host.']:'.$port;
    }

    private function readPrivateKeyMaterial(string $value): string
    {
        if ($value === '') {
            throw new RuntimeException('Management private key is missing.');
        }

        if (str_contains($value, 'PRIVATE KEY')) {
            return $value;
        }

        if (is_file($value) && is_readable($value)) {
            return File::get($value);
        }

        $result = Process::timeout(5)->run(['sudo', 'cat', '--', $value]);

        if (! $result->successful() || blank($result->output())) {
            throw new RuntimeException('Unable to read the management private key.');
        }

        return $result->output();
    }

    private function restrictPrivateKey(string $path): void
    {
        File::chmod($path, 0600);
    }
}
