<?php

namespace App\Operations\Aftermath;

use App\Enums\SiteStatus;
use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\Release;
use App\Models\Server;
use App\Models\Site;
use App\Operations\Aftermath\Contracts\HandlesFailedOperation;
use App\Operations\Aftermath\Contracts\StepAftermath;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use App\Support\StepExecutionResult;

final class FinalizeSiteAftermath implements HandlesFailedOperation, StepAftermath
{
    public function __construct(private SshService $ssh) {}

    public static function key(): string
    {
        return 'finalize_site';
    }

    public function handle(
        Server $server,
        Operation $operation,
        OperationStep $step,
        StepExecutionResult $result,
    ): void {
        if (! $result->success) {
            return;
        }

        $this->succeeded($operation);
    }

    public function failed(Operation $operation, ?string $message): void
    {
        if (! $this->appliesTo($operation)) {
            return;
        }

        $site = $this->site($operation);

        if ($site === null) {
            return;
        }

        $commitSha = $this->commitSha($operation);

        $activateSucceeded = $operation->steps()
            ->where('recipe', 'deploy.activate@v1')
            ->where('status', 'succeeded')
            ->exists();

        $previousSha = $this->previousSha($operation);

        if ($activateSucceeded && is_string($previousSha) && $previousSha !== '') {
            $this->restoreSymlink($site, $previousSha);
            $this->restorePreviousRelease($site, $previousSha);
        } else {
            $site->forceFill([
                'status' => SiteStatus::FAILED,
                'failure_message' => $message,
            ])->save();
        }

        if ($commitSha === null) {
            return;
        }

        $release = $this->release($operation, $site, $commitSha);

        if ($release === null) {
            return;
        }

        if ($operation->type === 'deploy') {
            $release->forceFill([
                'status' => 'failed',
                'failure_message' => $message,
            ])->save();
        }

        if ($operation->type === 'rollback') {
            $release->forceFill([
                'status' => $release->activated_at ? 'rolled_back' : 'failed',
                'failure_message' => $message,
            ])->save();
        }
    }

    private function succeeded(Operation $operation): void
    {
        if (! $this->appliesTo($operation)) {
            return;
        }

        $site = $this->site($operation);

        if ($site === null) {
            return;
        }

        $commitSha = $this->commitSha($operation);

        if ($commitSha === null) {
            return;
        }

        $release = $this->release($operation, $site, $commitSha);

        if ($release === null) {
            return;
        }

        Release::query()
            ->where('site_id', $site->id)
            ->where('id', '!=', $release->id)
            ->where('status', 'active')
            ->update(['status' => 'rolled_back']);

        $release->forceFill([
            'status' => 'active',
            'failure_message' => null,
            'activated_at' => $release->activated_at ?? now(),
        ])->save();

        $site->forceFill([
            'current_release_id' => $release->id,
            'last_deployed_at' => now(),
            'status' => SiteStatus::DEPLOYED,
            'failure_message' => null,
        ])->save();
    }

    private function appliesTo(Operation $operation): bool
    {
        return in_array($operation->type, ['deploy', 'rollback'], true);
    }

    private function commitSha(Operation $operation): ?string
    {
        $commitSha = data_get($operation->plan_snapshot, 'commit_sha');

        return is_string($commitSha) ? $commitSha : null;
    }

    private function previousSha(Operation $operation): ?string
    {
        $firstStepArgs = $operation->steps()->orderBy('position')->first()?->arguments;

        if (is_array($firstStepArgs) && is_string($firstStepArgs['previous_sha'] ?? null) && $firstStepArgs['previous_sha'] !== '') {
            return $firstStepArgs['previous_sha'];
        }

        return null;
    }

    private function site(Operation $operation): ?Site
    {
        $siteId = data_get($operation->plan_snapshot, 'site_id');

        if (! is_int($siteId) && ! is_numeric($siteId)) {
            return null;
        }

        return Site::query()->with(['server', 'currentRelease'])->find((int) $siteId);
    }

    private function release(Operation $operation, Site $site, string $commitSha): ?Release
    {
        $releaseId = data_get($operation->plan_snapshot, 'release_id');

        if (is_int($releaseId) || is_numeric($releaseId)) {
            return Release::query()
                ->where('site_id', $site->id)
                ->whereKey((int) $releaseId)
                ->first();
        }

        return Release::query()
            ->where('site_id', $site->id)
            ->where('operation_id', $operation->id)
            ->where('commit_sha', $commitSha)
            ->latest('id')
            ->first();
    }

    private function restorePreviousRelease(Site $site, string $previousSha): void
    {
        $previous = Release::query()
            ->where('site_id', $site->id)
            ->where('commit_sha', $previousSha)
            ->latest('id')
            ->first();

        if ($previous === null) {
            return;
        }

        $previous->forceFill([
            'status' => 'active',
            'failure_message' => null,
        ])->save();

        $site->forceFill([
            'current_release_id' => $previous->id,
            'status' => SiteStatus::DEPLOYED,
            'failure_message' => null,
        ])->save();
    }

    private function restoreSymlink(Site $site, string $previousSha): void
    {
        $server = $site->server;
        $rootPath = $site->root_path;

        if ($server === null || ! is_string($server->host_key) || $server->host_key === '' || ! is_string($rootPath) || $rootPath === '') {
            return;
        }

        $root = escapeshellarg($rootPath);
        $sha = escapeshellarg($previousSha);
        $script = <<<BASH
set -euo pipefail
ROOT={$root}
SHA={$sha}
if [[ -d "\${ROOT}/releases/\${SHA}" ]]; then
  ln -sfn "releases/\${SHA}" "\${ROOT}/current"
fi
BASH;

        $this->ssh->run(
            $server,
            new HostFingerprint($server->host_key, 'SHA256:confirmed'),
            $script,
            60,
        );
    }
}
