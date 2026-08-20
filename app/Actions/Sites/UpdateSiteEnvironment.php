<?php

namespace App\Actions\Sites;

use App\Actions\Sites\Concerns\EnsuresSiteEnvironmentIsEditable;
use App\Models\Site;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use Illuminate\Validation\ValidationException;

class UpdateSiteEnvironment
{
    use EnsuresSiteEnvironmentIsEditable;

    public const MaxBytes = 262_144;

    public function __construct(private SshService $ssh) {}

    public function handle(User $user, Site $site, string $contents): void
    {
        $this->assertEditable($user, $site);

        if (strlen($contents) > self::MaxBytes) {
            throw ValidationException::withMessages([
                'contents' => 'The environment file may not be larger than 256 KB.',
            ]);
        }

        if (str_contains($contents, "\0")) {
            throw ValidationException::withMessages([
                'contents' => 'The environment file must be valid UTF-8 text.',
            ]);
        }

        $root = $site->root_path;
        $contentB64 = base64_encode($contents);

        $script = <<<BASH
set -euo pipefail
ROOT={$this->shellQuote($root)}
CONTENT_B64={$this->shellQuote($contentB64)}
ENV_FILE="\${ROOT}/shared/.env"
TMP_FILE="\${ENV_FILE}.tmp.\$\$"
mkdir -p "\${ROOT}/shared"
printf '%s' "\${CONTENT_B64}" | base64 -d > "\${TMP_FILE}"
chmod 640 "\${TMP_FILE}" || true
sudo -n chgrp www-data "\${TMP_FILE}" 2>/dev/null || chgrp www-data "\${TMP_FILE}" 2>/dev/null || true
mv -f "\${TMP_FILE}" "\${ENV_FILE}"
sudo -n chgrp www-data "\${ENV_FILE}" 2>/dev/null || chgrp www-data "\${ENV_FILE}" 2>/dev/null || true
chmod 640 "\${ENV_FILE}" || true
echo ENV_UPDATED
BASH;

        $server = $site->server;
        $host = new HostFingerprint((string) $server->host_key, 'SHA256:confirmed');
        $result = $this->ssh->run($server, $host, $script, 60);

        if (! $result->successful() || ! str_contains($result->output, 'ENV_UPDATED')) {
            throw ValidationException::withMessages([
                'contents' => 'Unable to update the remote environment file.',
            ]);
        }
    }

    private function shellQuote(string $value): string
    {
        return escapeshellarg($value);
    }
}
