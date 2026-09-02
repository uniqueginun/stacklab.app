<?php

namespace App\Actions\Operations\Concerns;

use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait DispatchesQueueWorkerOperations
{
    private function assertSiteReady(Site $site): void
    {
        if (! $site->isLaravel()) {
            throw ValidationException::withMessages([
                'site' => 'Queue workers can only be managed for Laravel sites.',
            ]);
        }

        if ($site->status !== SiteStatus::DEPLOYED) {
            throw ValidationException::withMessages([
                'site' => 'Deploy the site before managing queue workers.',
            ]);
        }

        if ($site->current_release_id === null) {
            throw ValidationException::withMessages([
                'site' => 'Deploy the site before managing queue workers.',
            ]);
        }

        if (! $site->hasUsableRootPath()) {
            throw ValidationException::withMessages([
                'site' => 'The site path is not ready yet.',
            ]);
        }

        $site->loadMissing('server');

        if (! $site->server->isConnected()) {
            throw ValidationException::withMessages([
                'site' => 'Verify the server SSH connection before managing queue workers.',
            ]);
        }

        if (! is_string($site->server->ssh_user) || preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $site->server->ssh_user) !== 1) {
            throw ValidationException::withMessages([
                'site' => 'The server SSH user cannot be used to run queue workers.',
            ]);
        }
    }

    private function assertWorkerOnSite(Site $site, QueueWorker $worker): void
    {
        if ((int) $worker->site_id !== (int) $site->id) {
            throw ValidationException::withMessages([
                'queue_worker' => 'The selected queue worker is invalid.',
            ]);
        }
    }

    /**
     * @param  callable(QueueWorker, Site): array{steps: list<array{name: string, recipe: string, aftermath?: string, arguments: array<string, mixed>}>, snapshot?: array<string, mixed>}  $callback
     */
    private function dispatchQueueWorkerOperation(
        User $user,
        Site $site,
        QueueWorker $worker,
        string $type,
        callable $callback,
    ): Operation {
        $operation = DB::transaction(function () use ($user, $site, $worker, $type, $callback): Operation {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->id);
            $server = $lockedSite->server()->lockForUpdate()->firstOrFail();

            if ($server->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'site' => 'This server already has an active operation.',
                ]);
            }

            $lockedWorker = QueueWorker::query()
                ->where('site_id', $lockedSite->id)
                ->lockForUpdate()
                ->findOrFail($worker->id);

            if ($lockedWorker->isBusy()) {
                throw ValidationException::withMessages([
                    'queue_worker' => 'Wait for the current queue worker operation to finish.',
                ]);
            }

            $lockedWorker->setRelation('site', $lockedSite->setRelation('server', $server));

            $plan = $callback($lockedWorker, $lockedSite);
            $steps = $plan['steps'];
            $snapshot = $plan['snapshot'] ?? [];

            $operation = Operation::query()->create([
                'uuid' => (string) Str::uuid(),
                'server_id' => $server->id,
                'user_id' => $user->id,
                'type' => $type,
                'status' => 'pending',
                'plan_snapshot' => [
                    'site_id' => $lockedSite->id,
                    'site_public_id' => $lockedSite->uuid,
                    'queue_worker_id' => $lockedWorker->id,
                    'queue_worker_uuid' => $lockedWorker->uuid,
                    'steps' => $steps,
                    ...$snapshot,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            return $operation;
        }, attempts: 3);

        ProcessOperation::dispatch($operation->getKey());

        return $operation;
    }
}
