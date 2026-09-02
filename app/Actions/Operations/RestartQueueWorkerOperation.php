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

class RestartQueueWorkerOperation
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

        if (! $worker->canRestart()) {
            throw ValidationException::withMessages([
                'queue_worker' => 'Only installed queue workers can be restarted.',
            ]);
        }

        return $this->dispatchQueueWorkerOperation(
            $user,
            $site,
            $worker,
            'restart_queue_worker',
            function (QueueWorker $lockedWorker): array {
                $previous = $lockedWorker->snapshotAttributes();

                $lockedWorker->forceFill([
                    'status' => QueueWorkerStatus::Restarting,
                    'failure_message' => null,
                ])->save();

                $args = $this->recipe->arguments($lockedWorker);

                return [
                    'snapshot' => ['previous' => $previous],
                    'steps' => [
                        [
                            'name' => 'Restart queue worker',
                            'recipe' => 'queue_worker.restart@v1',
                            'aftermath' => FinalizeQueueWorkerAftermath::key(),
                            'arguments' => $args,
                        ],
                    ],
                ];
            },
        );
    }
}
