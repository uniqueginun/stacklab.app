<?php

namespace App\Actions\Operations;

use App\Actions\Operations\Concerns\DispatchesQueueWorkerOperations;
use App\Enums\QueueWorkerStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeQueueWorkerAftermath;
use App\Support\QueueWorkers\QueueWorkerRecipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateQueueWorkerOperation
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
    public function handle(User $user, Site $site, array $attributes): QueueWorker
    {
        $this->assertSiteReady($site);

        [$worker, $operation] = DB::transaction(function () use ($user, $site, $attributes): array {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->id);
            $server = $lockedSite->server()->lockForUpdate()->firstOrFail();

            if ($server->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'site' => 'This server already has an active operation.',
                ]);
            }

            $lockedSite->setRelation('server', $server);

            $worker = $lockedSite->queueWorkers()->create([
                ...$attributes,
                'status' => QueueWorkerStatus::Installing,
                'failure_message' => null,
            ]);

            $worker->setRelation('site', $lockedSite);
            $args = $this->recipe->arguments($worker);

            $steps = [
                [
                    'name' => 'Install Supervisor',
                    'recipe' => 'supervisor.install@v1',
                    'arguments' => $args,
                ],
                [
                    'name' => 'Install queue worker',
                    'recipe' => 'queue_worker.install@v1',
                    'aftermath' => FinalizeQueueWorkerAftermath::key(),
                    'arguments' => $args,
                ],
            ];

            $operation = Operation::query()->create([
                'uuid' => (string) Str::uuid(),
                'server_id' => $server->id,
                'user_id' => $user->id,
                'type' => 'install_queue_worker',
                'status' => 'pending',
                'plan_snapshot' => [
                    'site_id' => $lockedSite->id,
                    'site_public_id' => $lockedSite->uuid,
                    'queue_worker_id' => $worker->id,
                    'queue_worker_uuid' => $worker->uuid,
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            return [$worker, $operation];
        }, attempts: 3);

        ProcessOperation::dispatch($operation->getKey());

        return $worker;
    }
}
