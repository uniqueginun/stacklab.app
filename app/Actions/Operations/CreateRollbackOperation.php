<?php

namespace App\Actions\Operations;

use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Release;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeSiteAftermath;
use App\Support\SiteDeploymentOptions;
use App\Support\SiteNginxConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateRollbackOperation
{
    public function __construct(private SiteNginxConfig $nginxConfig) {}

    public function handle(User $user, Site $site, Release $release): Operation
    {
        if ($release->site_id !== $site->id) {
            throw ValidationException::withMessages([
                'release' => 'The selected release is invalid.',
            ]);
        }

        if ($site->current_release_id === $release->id) {
            throw ValidationException::withMessages([
                'release' => 'That release is already active.',
            ]);
        }

        if (! $release->canBeRolledBackTo($site->current_release_id)) {
            throw ValidationException::withMessages([
                'release' => 'Only a previously successful release can be restored.',
            ]);
        }

        $site->loadMissing('currentRelease');
        $previousSha = $site->currentRelease?->commit_sha;

        $operation = DB::transaction(function () use ($user, $site, $release, $previousSha): Operation {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->id);
            $server = $lockedSite->server()->lockForUpdate()->firstOrFail();

            if ($server->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'site' => 'This server already has an active operation.',
                ]);
            }

            $args = [
                'root_path' => $lockedSite->root_path,
                'repository' => (string) $lockedSite->repository_url,
                'branch' => (string) $lockedSite->repository_branch,
                'commit_sha' => $release->commit_sha,
                'domain' => $lockedSite->domain,
                'web_directory' => $lockedSite->web_directory,
                'site_type' => $lockedSite->type,
                'php_version' => $lockedSite->php_version,
                'ssh_user' => $server->ssh_user,
                'site_public_id' => $lockedSite->uuid,
                'previous_sha' => $previousSha ?? '',
                'retain' => 5,
                'release_id' => $release->id,
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
                ['name' => 'Activate release', 'recipe' => 'deploy.activate@v1', 'aftermath' => FinalizeSiteAftermath::key(), 'arguments' => $args],
                ['name' => 'Verify site', 'recipe' => 'deploy.verify@v1', 'arguments' => $args],
            ];

            $operation = Operation::query()->create([
                'uuid' => (string) Str::uuid(),
                'server_id' => $server->id,
                'user_id' => $user->id,
                'type' => 'rollback',
                'status' => 'pending',
                'plan_snapshot' => [
                    'site_id' => $lockedSite->id,
                    'site_public_id' => $lockedSite->uuid,
                    'commit_sha' => $release->commit_sha,
                    'release_id' => $release->id,
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            $release->forceFill([
                'operation_id' => $operation->id,
                'status' => 'deploying',
                'failure_message' => null,
            ])->save();

            $lockedSite->forceFill([
                'status' => SiteStatus::DEPLOYING,
                'failure_message' => null,
            ])->save();

            return $operation;
        }, attempts: 3);

        ProcessOperation::dispatch($operation->getKey());

        return $operation;
    }
}
