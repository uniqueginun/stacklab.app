<?php

namespace App\Actions\Sites;

use App\Actions\Servers\GenerateSshKeyPair;
use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use App\Support\GitHubApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AttachSiteRepository
{
    public function __construct(
        private GenerateSshKeyPair $generateSshKeyPair,
        private SshService $ssh,
    ) {}

    /**
     * @param  array{repository: string, branch: string}  $attributes
     */
    public function handle(User $user, Site $site, array $attributes): Site
    {
        $connection = $user->githubConnection;

        if ($connection === null) {
            throw ValidationException::withMessages([
                'repository' => 'Connect GitHub before attaching a repository.',
            ]);
        }

        $site->loadMissing('server');

        if ($site->status === SiteStatus::FAILED) {
            throw ValidationException::withMessages([
                'repository' => 'This site cannot attach a repository in its current status.',
            ]);
        }

        if (! is_string($site->server->host_key) || $site->server->host_key === ''
            || ! is_string($site->server->host_key_fingerprint) || $site->server->host_key_fingerprint === '') {
            throw ValidationException::withMessages([
                'repository' => 'The server does not have a confirmed SSH host key.',
            ]);
        }

        try {
            $rootPath = $site->ensureRootPath();
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'repository' => 'Unable to determine a filesystem path for this site.',
            ]);
        }

        [$owner, $repo] = $this->splitRepository($attributes['repository']);

        $api = new GitHubApi($connection);
        $repository = $api->repository($owner, $repo);
        $branches = collect($api->branches($owner, $repo))->pluck('name');

        if (! $branches->contains($attributes['branch'])) {
            throw ValidationException::withMessages([
                'branch' => 'The selected branch does not exist on this repository.',
            ]);
        }

        $keys = $this->generateSshKeyPair->handle('mini-forge-deploy-'.$site->domain);
        $fingerprint = $this->fingerprint($keys['public_key']);

        if ($site->deploy_key_id !== null && is_string($site->repository_url)) {
            [$previousOwner, $previousRepo] = $this->splitRepository($site->repository_url);
            $api->deleteDeployKey($previousOwner, $previousRepo, (int) $site->deploy_key_id);
        }

        $deployKey = $api->createDeployKey(
            $owner,
            $repo,
            'mini-forge:'.$site->domain,
            $keys['public_key'],
        );

        $result = $this->ssh->run(
            $site->server,
            HostFingerprint::fromServer($site->server),
            $this->installScript($rootPath, $site, $keys['private_key']),
            120,
        );

        if (! $result->successful() || ! str_contains($result->output, 'DEPLOY_KEY_INSTALLED')) {
            $api->deleteDeployKey($owner, $repo, $deployKey['id']);

            Log::warning('Unable to install the site deploy key over SSH.', [
                'site_id' => $site->id,
                'server_id' => $site->server_id,
                'exit_code' => $result->exitCode,
                'output' => $this->sshFailureDetail($result),
            ]);

            $detail = $this->sshFailureDetail($result);

            throw ValidationException::withMessages([
                'repository' => $detail === ''
                    ? 'Unable to install the deploy key on the server.'
                    : 'Unable to install the deploy key on the server: '.$detail,
            ]);
        }

        return DB::transaction(function () use ($site, $repository, $attributes, $deployKey, $fingerprint): Site {
            $site->forceFill([
                'repository_url' => $repository['full_name'],
                'repository_id' => $repository['id'],
                'repository_branch' => $attributes['branch'],
                'deploy_key_id' => $deployKey['id'],
                'deploy_key_fingerprint' => $fingerprint !== '' ? $fingerprint : ($deployKey['fingerprint'] ?: null),
            ])->save();

            return $site->fresh() ?? $site;
        });
    }

    /** @return array{0: string, 1: string} */
    private function splitRepository(string $repository): array
    {
        $parts = explode('/', $repository);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ValidationException::withMessages([
                'repository' => 'Repositories must use the owner/name format.',
            ]);
        }

        return [$parts[0], $parts[1]];
    }

    private function fingerprint(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey)) ?: [];

        if (count($parts) < 2) {
            return '';
        }

        $decoded = base64_decode($parts[1], true);

        if ($decoded === false) {
            return '';
        }

        return 'SHA256:'.rtrim(strtr(base64_encode(hash('sha256', $decoded, true)), '+/', '-_'), '=');
    }

    private function sshFailureDetail(SshResult $result): string
    {
        $combined = trim($result->errorOutput."\n".$result->output);
        $combined = preg_replace('/[A-Za-z0-9+\/=]{80,}/', '[redacted]', $combined) ?? $combined;

        $detail = Str::of($combined)
            ->replaceMatches('/DEPLOY_KEY_INSTALLED/', '')
            ->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->take(3)
            ->implode(' ');

        return Str::limit($detail, 180);
    }

    private function installScript(string $rootPath, Site $site, string $privateKey): string
    {
        $root = escapeshellarg($rootPath);
        $user = escapeshellarg($site->server->ssh_user);
        $keyB64 = base64_encode($privateKey);
        $isLaravel = strcasecmp((string) $site->type, 'laravel') === 0 ? 'true' : 'false';

        return <<<BASH
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:\${PATH:-}"

ROOT={$root}
SSH_USER={$user}
KEY_B64={$keyB64}
IS_LARAVEL={$isLaravel}

sudo -n mkdir -p "\${ROOT}/.ssh" "\${ROOT}/releases" "\${ROOT}/shared"
sudo -n chown -R "\${SSH_USER}:\${SSH_USER}" "\${ROOT}"
sudo -n chmod 755 "\${ROOT}"
sudo -n chmod 700 "\${ROOT}/.ssh"

printf '%s' "\${KEY_B64}" | base64 -d | sudo -n tee "\${ROOT}/.ssh/deploy_key" >/dev/null
sudo -n chmod 600 "\${ROOT}/.ssh/deploy_key"
sudo -n chown "\${SSH_USER}:\${SSH_USER}" "\${ROOT}/.ssh/deploy_key"

if [[ ! -s "\${ROOT}/shared/.env" ]]; then
  printf '' | sudo -n tee "\${ROOT}/shared/.env" >/dev/null
fi
sudo -n chmod 640 "\${ROOT}/shared/.env" || true
sudo -n chown "\${SSH_USER}:\${SSH_USER}" "\${ROOT}/shared/.env" || true

if [[ "\${IS_LARAVEL}" == "true" ]]; then
  sudo -n mkdir -p "\${ROOT}/shared/storage/app/public" \
    "\${ROOT}/shared/storage/framework/cache" \
    "\${ROOT}/shared/storage/framework/sessions" \
    "\${ROOT}/shared/storage/framework/views" \
    "\${ROOT}/shared/storage/logs"
  sudo -n chown -R "\${SSH_USER}:\${SSH_USER}" "\${ROOT}/shared"
fi

echo "DEPLOY_KEY_INSTALLED"
BASH;
    }
}
