<?php

namespace App\Actions\Sites;

use App\Actions\Sites\Concerns\EnsuresSiteEnvironmentIsEditable;
use App\Models\Site;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use Illuminate\Validation\ValidationException;

class ReadSiteEnvironment
{
    use EnsuresSiteEnvironmentIsEditable;

    public function __construct(private SshService $ssh) {}

    public function handle(User $user, Site $site): string
    {
        $this->assertEditable($user, $site);

        $root = $site->root_path;
        $script = <<<BASH
set -euo pipefail
ROOT={$this->shellQuote($root)}
ENV_FILE="\${ROOT}/shared/.env"
mkdir -p "\${ROOT}/shared"
if [[ ! -f "\${ENV_FILE}" ]]; then
  printf '' > "\${ENV_FILE}"
  chmod 640 "\${ENV_FILE}" || true
fi
echo ENV_B64_BEGIN
base64 -w0 < "\${ENV_FILE}"
echo
echo ENV_B64_END
BASH;

        $server = $site->server;
        $host = new HostFingerprint((string) $server->host_key, 'SHA256:confirmed');
        $result = $this->ssh->run($server, $host, $script, 60);

        if (! $result->successful()) {
            throw ValidationException::withMessages([
                'contents' => 'Unable to read the remote environment file.',
            ]);
        }

        if (! preg_match('/ENV_B64_BEGIN\n([A-Za-z0-9+\/=]*)\nENV_B64_END/', $result->output, $matches)) {
            throw ValidationException::withMessages([
                'contents' => 'Unable to parse the remote environment file.',
            ]);
        }

        $decoded = base64_decode($matches[1], true);

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'contents' => 'Unable to decode the remote environment file.',
            ]);
        }

        return $decoded;
    }

    private function shellQuote(string $value): string
    {
        return escapeshellarg($value);
    }
}
