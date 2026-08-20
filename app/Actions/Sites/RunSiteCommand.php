<?php

namespace App\Actions\Sites;

use App\Enums\ConnectionStatus;
use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunSiteCommand
{
    public const int TimeoutSeconds = 120;

    public const int MaxOutputBytes = 65_536;

    public function __construct(private SshService $ssh) {}

    /**
     * @return array{
     *     command: string,
     *     working_directory: string,
     *     exit_code: int,
     *     output: string
     * }
     */
    public function handle(User $user, Site $site, string $command): array
    {
        $this->assertRunnable($user, $site);

        $root = $site->root_path;
        $commandB64 = base64_encode($command);
        $phpBin = $site->phpBinary();

        $script = <<<BASH
set -euo pipefail
ROOT={$this->shellQuote((string) $root)}
COMMAND_B64={$this->shellQuote($commandB64)}
PHP_BIN={$this->shellQuote($phpBin)}
CURRENT="\${ROOT}/current"
SHIM=""
OUT_FILE=""

cleanup() {
  if [[ -n "\${SHIM}" && -d "\${SHIM}" ]]; then
    rm -rf "\${SHIM}"
  fi
  if [[ -n "\${OUT_FILE}" && -f "\${OUT_FILE}" ]]; then
    rm -f "\${OUT_FILE}"
  fi
}
trap cleanup EXIT

if [[ ! -e "\${CURRENT}" ]]; then
  echo CMD_ERROR:missing_current
  exit 1
fi

cd "\${CURRENT}" || {
  echo CMD_ERROR:cd_failed
  exit 1
}

if command -v "\${PHP_BIN}" >/dev/null 2>&1; then
  SHIM="\$(mktemp -d)"
  ln -sf "\$(command -v "\${PHP_BIN}")" "\${SHIM}/php"
  export PATH="\${SHIM}:\${PATH}"
fi

COMMAND="\$(printf '%s' "\${COMMAND_B64}" | base64 -d)"
OUT_FILE="\$(mktemp)"

set +e
bash -c "\${COMMAND}" >"\${OUT_FILE}" 2>&1
EXIT=\$?
set -e

echo CMD_EXIT:\${EXIT}
echo CMD_OUTPUT_B64_BEGIN
head -c {$this->shellQuote((string) self::MaxOutputBytes)} "\${OUT_FILE}" | base64 -w0
echo
echo CMD_OUTPUT_B64_END
BASH;

        $server = $site->server;
        $host = new HostFingerprint((string) $server->host_key, 'SHA256:confirmed');

        try {
            $result = $this->ssh->run($server, $host, $script, self::TimeoutSeconds);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'command' => 'Unable to run the command on the server.',
            ]);
        }

        if (str_contains($result->output, 'CMD_ERROR:missing_current') || str_contains($result->output, 'CMD_ERROR:cd_failed')) {
            throw ValidationException::withMessages([
                'command' => 'The current release directory is missing. Deploy the site before running commands.',
            ]);
        }

        if (! preg_match('/CMD_EXIT:(\d+)\nCMD_OUTPUT_B64_BEGIN\n([A-Za-z0-9+\/=]*)\nCMD_OUTPUT_B64_END/', $result->output, $matches)) {
            throw ValidationException::withMessages([
                'command' => 'Unable to parse the command output from the server.',
            ]);
        }

        $decoded = base64_decode($matches[2], true);

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'command' => 'Unable to decode the command output from the server.',
            ]);
        }

        return [
            'command' => $command,
            'working_directory' => $site->currentPath(),
            'exit_code' => (int) $matches[1],
            'output' => $decoded,
        ];
    }

    private function assertRunnable(User $user, Site $site): void
    {
        if (! $user->is($site->user)) {
            throw ValidationException::withMessages([
                'site' => 'The selected site is invalid.',
            ]);
        }

        if (! $site->isLaravel()) {
            throw ValidationException::withMessages([
                'site' => 'Commands can only be run on Laravel sites.',
            ]);
        }

        if ($site->status !== SiteStatus::DEPLOYED) {
            throw ValidationException::withMessages([
                'site' => 'The site must be deployed before running commands.',
            ]);
        }

        if ($site->current_release_id === null) {
            throw ValidationException::withMessages([
                'site' => 'Deploy the site before running commands.',
            ]);
        }

        if (! $site->hasUsableRootPath()) {
            throw ValidationException::withMessages([
                'site' => 'The site path is not ready yet.',
            ]);
        }

        $site->loadMissing('server');
        $server = $site->server;

        if ($server->connection_status !== ConnectionStatus::CONNECTED || ! is_string($server->host_key) || $server->host_key === '') {
            throw ValidationException::withMessages([
                'site' => 'Verify the server SSH connection before running commands.',
            ]);
        }
    }

    private function shellQuote(string $value): string
    {
        return escapeshellarg($value);
    }
}
