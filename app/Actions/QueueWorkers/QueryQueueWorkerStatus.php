<?php

namespace App\Actions\QueueWorkers;

use App\Enums\ConnectionStatus;
use App\Enums\QueueWorkerStatus;
use App\Models\Site;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use App\Support\QueueWorkers\QueueWorkerRuntimeStatusParser;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueryQueueWorkerStatus
{
    public const int TimeoutSeconds = 30;

    public function __construct(
        private SshService $ssh,
        private QueueWorkerRuntimeStatusParser $parser,
    ) {}

    /**
     * @return array{
     *     workers: array<string, array{
     *         configured_processes: int,
     *         running_processes: int,
     *         states: array<string, int>,
     *         healthy: bool,
     *         checked_at: string,
     *         missing: bool
     *     }>,
     *     error: string|null
     * }
     */
    public function handle(User $user, Site $site): array
    {
        $this->assertReadable($user, $site);

        $workers = $site->queueWorkers()
            ->where('status', QueueWorkerStatus::Installed)
            ->orderBy('id')
            ->get();

        if ($workers->isEmpty()) {
            return [
                'workers' => [],
                'error' => null,
            ];
        }

        $configured = [];

        foreach ($workers as $worker) {
            $configured[$worker->supervisorProgram()] = (int) $worker->processes;
        }

        $programs = implode(',', array_keys($configured));

        $script = <<<'BASH'
set -euo pipefail
IFS=',' read -r -a PROGRAMS <<< "${PROGRAMS}"
for program in "${PROGRAMS[@]}"; do
  echo "STACKLAB_PROGRAM_BEGIN:${program}"
  sudo -n supervisorctl status "${program}:*" 2>&1 || true
  echo "STACKLAB_PROGRAM_END:${program}"
done
BASH;

        $script = 'PROGRAMS='.escapeshellarg($programs).PHP_EOL.$script;

        try {
            $result = $this->ssh->run(
                $site->server,
                HostFingerprint::fromServer($site->server),
                $script,
                self::TimeoutSeconds,
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'site' => 'Unable to query Supervisor status on the server.',
            ]);
        }

        $parsed = $this->parser->parseGroups($result->output."\n".$result->errorOutput, $configured);
        $payload = [];

        foreach ($workers as $worker) {
            $payload[$worker->uuid] = $parsed[$worker->supervisorProgram()] ?? $this->parser->parse('', (int) $worker->processes);
        }

        return [
            'workers' => $payload,
            'error' => null,
        ];
    }

    private function assertReadable(User $user, Site $site): void
    {
        if (! $user->is($site->user)) {
            throw ValidationException::withMessages([
                'site' => 'The selected site is invalid.',
            ]);
        }

        $site->loadMissing('server');
        $server = $site->server;

        if ($server->connection_status !== ConnectionStatus::CONNECTED || ! is_string($server->host_key) || $server->host_key === '') {
            throw ValidationException::withMessages([
                'site' => 'Verify the server SSH connection before checking queue workers.',
            ]);
        }
    }
}
