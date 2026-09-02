<?php

namespace App\Actions\QueueWorkers;

use App\Enums\ConnectionStatus;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use App\Support\QueueWorkers\QueueWorkerLogRedactor;
use App\Support\QueueWorkers\QueueWorkerSettings;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReadQueueWorkerLogs
{
    public const int TimeoutSeconds = 30;

    public function __construct(
        private SshService $ssh,
        private QueueWorkerLogRedactor $redactor,
    ) {}

    /**
     * @return array{output: string, truncated: bool}
     */
    public function handle(User $user, Site $site, QueueWorker $worker): array
    {
        $this->assertReadable($user, $site, $worker);

        $logPath = $worker->stdoutLogPath();
        $maxBytes = QueueWorkerSettings::LogMaxBytes;
        $maxLines = QueueWorkerSettings::LogMaxLines;

        $script = <<<BASH
set -euo pipefail
LOG_PATH={$this->shellQuote($logPath)}
MAX_BYTES={$this->shellQuote((string) $maxBytes)}
MAX_LINES={$this->shellQuote((string) $maxLines)}

if [[ ! "\${LOG_PATH}" =~ /shared/logs/worker-[0-9]+\\.log$ ]]; then
  echo LOG_ERROR:invalid_path
  exit 1
fi

if [[ ! -f "\${LOG_PATH}" ]]; then
  echo LOG_MISSING
  exit 0
fi

TMP="\$(mktemp)"
trap 'rm -f "\${TMP}"' EXIT
tail -n "\${MAX_LINES}" "\${LOG_PATH}" > "\${TMP}"
BYTES="\$(wc -c < "\${TMP}" | tr -d ' ')"

echo LOG_BYTES:\${BYTES}
echo LOG_OUTPUT_B64_BEGIN
head -c "\${MAX_BYTES}" "\${TMP}" | base64 -w0
echo
echo LOG_OUTPUT_B64_END
BASH;

        try {
            $result = $this->ssh->run(
                $site->server,
                HostFingerprint::fromServer($site->server),
                $script,
                self::TimeoutSeconds,
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'queue_worker' => 'Unable to read queue worker logs on the server.',
            ]);
        }

        $output = $result->output."\n".$result->errorOutput;

        if (str_contains($output, 'LOG_ERROR:invalid_path')) {
            throw ValidationException::withMessages([
                'queue_worker' => 'The worker log path is invalid.',
            ]);
        }

        if (str_contains($output, 'LOG_MISSING')) {
            return [
                'output' => '',
                'truncated' => false,
            ];
        }

        if (! preg_match('/LOG_BYTES:(\d+)\nLOG_OUTPUT_B64_BEGIN\n([A-Za-z0-9+\/=]*)\nLOG_OUTPUT_B64_END/', $output, $matches)) {
            throw ValidationException::withMessages([
                'queue_worker' => 'Unable to parse queue worker logs from the server.',
            ]);
        }

        $decoded = base64_decode($matches[2], true);

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'queue_worker' => 'Unable to decode queue worker logs from the server.',
            ]);
        }

        return [
            'output' => $this->redactor->redact($decoded),
            'truncated' => (int) $matches[1] > $maxBytes,
        ];
    }

    private function assertReadable(User $user, Site $site, QueueWorker $worker): void
    {
        if (! $user->is($site->user) || (int) $worker->site_id !== (int) $site->id) {
            throw ValidationException::withMessages([
                'site' => 'The selected site is invalid.',
            ]);
        }

        $site->loadMissing('server');
        $server = $site->server;

        if ($server->connection_status !== ConnectionStatus::CONNECTED || ! is_string($server->host_key) || $server->host_key === '') {
            throw ValidationException::withMessages([
                'site' => 'Verify the server SSH connection before reading queue worker logs.',
            ]);
        }
    }

    private function shellQuote(string $value): string
    {
        return escapeshellarg($value);
    }
}
