<?php

namespace App\Actions\Operations;

use App\Actions\Operations\Concerns\DispatchesQueueWorkerOperations;
use App\Enums\QueueWorkerStatus;
use App\Models\Operation;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeQueueWorkerAftermath;
use App\Support\QueueWorkers\QueueWorkerRecipe;
use Illuminate\Validation\ValidationException;

class UpdateQueueWorkerOperation
{
    use DispatchesQueueWorkerOperations;

    public function __construct(private QueueWorkerRecipe $recipe) {}

    /**
     * @param  array{
     *     name: string,
     *     connection: string,
     *     queue: string,
     *     php_version: string,
     *     processes: int,
     *     sleep: int,
     *     timeout: int,
     *     tries: int,
     *     backoff: int,
     *     max_jobs: int,
     *     max_time: int,
     *     stopwaitsecs: int,
     *     restart_on_deploy: bool
     * }  $attributes
     */
    public function handle(User $user, Site $site, QueueWorker $worker, array $attributes): ?Operation
    {
        $this->assertWorkerOnSite($site, $worker);
        $this->assertSiteReady($site);

        if ($worker->isBusy()) {
            throw ValidationException::withMessages([
                'queue_worker' => 'Wait for the current queue worker operation to finish.',
            ]);
        }

        if (! $worker->canUpdate()) {
            throw ValidationException::withMessages([
                'queue_worker' => 'This queue worker cannot be updated right now.',
            ]);
        }

        if (! $this->recipe->configWouldChange($worker, $attributes)) {
            $worker->forceFill([
                'name' => $attributes['name'],
                'restart_on_deploy' => $attributes['restart_on_deploy'],
            ])->save();

            return null;
        }

        return $this->dispatchQueueWorkerOperation(
            $user,
            $site,
            $worker,
            'update_queue_worker',
            function (QueueWorker $lockedWorker) use ($attributes): array {
                $previous = $lockedWorker->snapshotAttributes();

                $lockedWorker->forceFill([
                    ...$attributes,
                    'status' => QueueWorkerStatus::Updating,
                    'failure_message' => null,
                ])->save();

                $args = $this->recipe->arguments($lockedWorker);

                return [
                    'snapshot' => ['previous' => $previous],
                    'steps' => [
                        [
                            'name' => 'Update queue worker',
                            'recipe' => 'queue_worker.update@v1',
                            'aftermath' => FinalizeQueueWorkerAftermath::key(),
                            'arguments' => $args,
                        ],
                    ],
                ];
            },
        );
    }
}
