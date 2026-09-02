<?php

namespace App\Actions\Operations;

use App\Enums\QueueWorkerStatus;
use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Models\User;
use App\Operations\Aftermath\FinalizeQueueWorkerAftermath;
use App\Support\QueueWorkers\QueueWorkerCommandBuilder;
use App\Support\QueueWorkers\SupervisorQueueWorkerConfigBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateQueueWorkerOperation
{
    public function __construct(
        private QueueWorkerCommandBuilder $commands,
        private SupervisorQueueWorkerConfigBuilder $supervisor,
    ) {}

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

            $command = $this->commands->build([
                'connection' => $worker->connection,
                'queue' => $worker->queue,
                'php_version' => $worker->php_version,
                'sleep' => (int) $worker->sleep,
                'timeout' => (int) $worker->timeout,
                'tries' => (int) $worker->tries,
                'backoff' => (int) $worker->backoff,
                'max_jobs' => (int) $worker->max_jobs,
                'max_time' => (int) $worker->max_time,
                'artisan_path' => $worker->artisanPath(),
            ]);

            $config = $this->supervisor->build([
                'program' => $worker->supervisorProgram(),
                'command' => $command,
                'directory' => $worker->workingDirectory(),
                'user' => (string) $server->ssh_user,
                'processes' => (int) $worker->processes,
                'stopwaitsecs' => (int) $worker->stopwaitsecs,
                'stdout_logfile' => $worker->stdoutLogPath(),
            ]);

            $args = [
                'root_path' => $lockedSite->root_path,
                'php_version' => $worker->php_version,
                'php_binary' => $this->commands->phpBinaryPath($worker->php_version),
                'artisan_path' => $worker->artisanPath(),
                'site_user' => $server->ssh_user,
                'processes' => (string) $worker->processes,
                'supervisor_program' => $worker->supervisorProgram(),
                'supervisor_config_path' => $worker->supervisorConfigPath(),
                'supervisor_config_b64' => base64_encode($config),
                'worker_log_path' => $worker->stdoutLogPath(),
                'queue_worker_id' => $worker->id,
            ];

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

    private function assertSiteReady(Site $site): void
    {
        if (! $site->isLaravel()) {
            throw ValidationException::withMessages([
                'site' => 'Queue workers can only be created for Laravel sites.',
            ]);
        }

        if ($site->status !== SiteStatus::DEPLOYED) {
            throw ValidationException::withMessages([
                'site' => 'Deploy the site before creating a queue worker.',
            ]);
        }

        if ($site->current_release_id === null) {
            throw ValidationException::withMessages([
                'site' => 'Deploy the site before creating a queue worker.',
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
                'site' => 'Verify the server SSH connection before creating a queue worker.',
            ]);
        }

        if (! is_string($site->server->ssh_user) || preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $site->server->ssh_user) !== 1) {
            throw ValidationException::withMessages([
                'site' => 'The server SSH user cannot be used to run queue workers.',
            ]);
        }
    }
}
