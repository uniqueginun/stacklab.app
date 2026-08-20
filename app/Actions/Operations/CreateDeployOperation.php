<?php

namespace App\Actions\Operations;

use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Release;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeSiteAftermath;
use App\Support\GitHubApi;
use App\Support\SiteDeploymentOptions;
use App\Support\SiteNginxConfig;
use App\Support\SupportedPlatforms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDeployOperation
{
    public function __construct(private SiteNginxConfig $nginxConfig) {}

    /**
     * @param  array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }|null  $options
     */
    public function handle(User $user, Site $site, ?array $options = null, ?string $commitSha = null): Operation
    {
        if ($site->status === SiteStatus::DEPLOYING) {
            throw ValidationException::withMessages([
                'site' => 'A deployment is already in progress.',
            ]);
        }

        if (! is_string($site->repository_url) || $site->repository_url === '' || ! is_string($site->repository_branch) || $site->repository_branch === '') {
            throw ValidationException::withMessages([
                'site' => 'Attach a GitHub repository before deploying.',
            ]);
        }

        $connection = $user->githubConnection;

        if ($connection === null) {
            throw ValidationException::withMessages([
                'site' => 'Connect GitHub before deploying.',
            ]);
        }

        if ($options !== null && $site->isLaravel()) {
            $site->forceFill([
                'deployment_options' => SiteDeploymentOptions::normalize($options),
            ])->save();
        }

        [$owner, $repo] = $this->splitRepository($site->repository_url);
        $api = new GitHubApi($connection);
        $head = $commitSha === null
            ? $api->branchHead($owner, $repo, $site->repository_branch)
            : ['sha' => $commitSha, 'message' => null];

        $previousSha = $site->currentRelease?->commit_sha;

        $operation = DB::transaction(function () use ($user, $site, $head, $previousSha): Operation {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->id);
            $server = $lockedSite->server()->lockForUpdate()->firstOrFail();

            if ($server->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'site' => 'This server already has an active operation.',
                ]);
            }

            if (! is_string($lockedSite->php_version) || $lockedSite->php_version === '') {
                $lockedSite->forceFill([
                    'php_version' => $server->provisionedPhpVersion()
                        ?? SupportedPlatforms::defaultPhpVersionFor($server),
                ])->save();
            }

            $lockedSite->setRelation('server', $server);

            $args = [
                'root_path' => $lockedSite->root_path,
                'repository' => (string) $lockedSite->repository_url,
                'branch' => (string) $lockedSite->repository_branch,
                'commit_sha' => $head['sha'],
                'domain' => $lockedSite->domain,
                'web_directory' => $lockedSite->web_directory,
                'site_type' => $lockedSite->type,
                'php_version' => $lockedSite->php_version,
                'ssh_user' => $server->ssh_user,
                'site_public_id' => $lockedSite->uuid,
                'previous_sha' => $previousSha ?? '',
                'retain' => 5,
                'nginx_config_b64' => base64_encode(
                    $this->nginxConfig->serverBlock(
                        $lockedSite,
                        $this->nginxConfig->documentRoot($lockedSite, useCurrentSymlink: true),
                    ),
                ),
                ...SiteDeploymentOptions::toRecipeArguments(
                    $lockedSite->resolvedDeploymentOptions(),
                ),
            ];

            $steps = [
                ['name' => 'Clone release', 'recipe' => 'deploy.clone@v1', 'arguments' => $args],
                ['name' => 'Link shared resources', 'recipe' => 'deploy.link_shared@v1', 'arguments' => $args],
                ['name' => 'Build release', 'recipe' => 'deploy.build@v1', 'arguments' => $args],
                ['name' => 'Run deploy hook', 'recipe' => 'deploy.hook@v1', 'arguments' => $args],
                ['name' => 'Activate release', 'recipe' => 'deploy.activate@v1', 'aftermath' => FinalizeSiteAftermath::key(), 'arguments' => $args],
                ['name' => 'Verify site', 'recipe' => 'deploy.verify@v1', 'arguments' => $args],
                ['name' => 'Prune old releases', 'recipe' => 'deploy.prune@v1', 'arguments' => $args],
            ];

            $operation = Operation::query()->create([
                'uuid' => (string) Str::uuid(),
                'server_id' => $server->id,
                'user_id' => $user->id,
                'type' => 'deploy',
                'status' => 'pending',
                'plan_snapshot' => [
                    'site_id' => $lockedSite->id,
                    'site_public_id' => $lockedSite->uuid,
                    'commit_sha' => $head['sha'],
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            $release = Release::query()->create([
                'uuid' => (string) Str::uuid(),
                'site_id' => $lockedSite->id,
                'user_id' => $user->id,
                'operation_id' => $operation->id,
                'commit_sha' => $head['sha'],
                'commit_message' => $head['message'],
                'status' => 'deploying',
            ]);

            $lockedSite->forceFill([
                'status' => SiteStatus::DEPLOYING,
                'failure_message' => null,
            ])->save();

            $snapshot = $operation->plan_snapshot;

            $operation->forceFill([
                'plan_snapshot' => [
                    ...(is_array($snapshot) ? $snapshot : []),
                    'release_id' => $release->id,
                ],
            ])->save();

            return $operation;
        }, attempts: 3);

        ProcessOperation::dispatch($operation->getKey());

        return $operation;
    }

    /** @return array{0: string, 1: string} */
    private function splitRepository(string $repository): array
    {
        $parts = explode('/', $repository);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ValidationException::withMessages([
                'site' => 'Attach a GitHub repository before deploying.',
            ]);
        }

        return [$parts[0], $parts[1]];
    }
}
