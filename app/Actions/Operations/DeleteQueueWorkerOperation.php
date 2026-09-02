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

class DeleteQueueWorkerOperation
{
    use DispatchesQueueWorkerOperations;

    public function __construct(private QueueWorkerRecipe $recipe) {}

    public function handle(User $user, Site $site, QueueWorker $worker): Operation
    {
        $this->assertWorkerOnSite($site, $worker);
        $this->assertSiteReady($site);

        if ($worker->isBusy()) {
            throw ValidationException::withMessages([
                'queue_worker' => 'Wait for the current queue worker operation to finish.',
            ]);
        }

        if (! $worker->canDelete()) {
            throw ValidationException::withMessages([
                'queue_worker' => 'This queue worker cannot be deleted right now.',
            ]);
        }

        return $this->dispatchQueueWorkerOperation(
            $user,
            $site,
            $worker,
            'delete_queue_worker',
            function (QueueWorker $lockedWorker): array {
                $previous = $lockedWorker->snapshotAttributes();

                $lockedWorker->forceFill([
                    'status' => QueueWorkerStatus::Deleting,
                    'failure_message' => null,
                ])->save();

                $args = $this->recipe->arguments($lockedWorker);

                return [
                    'snapshot' => ['previous' => $previous],
                    'steps' => [
                        [
                            'name' => 'Remove queue worker',
                            'recipe' => 'queue_worker.delete@v1',
                            'aftermath' => FinalizeQueueWorkerAftermath::key(),
                            'arguments' => $args,
                        ],
                    ],
                ];
            },
        );
    }
}
