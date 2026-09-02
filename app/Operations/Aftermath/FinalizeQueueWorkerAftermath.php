<?php

namespace App\Operations\Aftermath;

use App\Enums\QueueWorkerStatus;
use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\QueueWorker;
use App\Models\Server;
use App\Operations\Aftermath\Contracts\HandlesFailedOperation;
use App\Operations\Aftermath\Contracts\StepAftermath;
use App\Support\StepExecutionResult;

final class FinalizeQueueWorkerAftermath implements HandlesFailedOperation, StepAftermath
{
    public static function key(): string
    {
        return 'finalize_queue_worker';
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

        $worker = $this->worker($operation);

        if ($worker === null) {
            return;
        }

        if ($operation->type === 'delete_queue_worker') {
            $worker->delete();

            return;
        }

        $worker->forceFill([
            'status' => QueueWorkerStatus::Installed,
            'failure_message' => null,
            'installed_at' => in_array($operation->type, ['install_queue_worker', 'update_queue_worker'], true)
                ? now()
                : ($worker->installed_at ?? now()),
        ])->save();
    }

    public function failed(Operation $operation, ?string $message): void
    {
        $worker = $this->worker($operation);

        if ($worker === null) {
            return;
        }

        match ($operation->type) {
            'install_queue_worker' => $worker->forceFill([
                'status' => QueueWorkerStatus::Failed,
                'failure_message' => $message,
            ])->save(),
            'update_queue_worker', 'delete_queue_worker' => $this->restorePrevious($worker, $operation, $message),
            'restart_queue_worker', 'graceful_restart_queue_worker' => $worker->forceFill([
                'status' => QueueWorkerStatus::Installed,
                'failure_message' => $message,
            ])->save(),
            default => null,
        };
    }

    private function restorePrevious(QueueWorker $worker, Operation $operation, ?string $message): void
    {
        $snapshot = data_get($operation->plan_snapshot, 'previous');

        if (! is_array($snapshot)) {
            $worker->forceFill([
                'status' => QueueWorkerStatus::Failed,
                'failure_message' => $message,
            ])->save();

            return;
        }

        $status = QueueWorkerStatus::tryFrom((string) ($snapshot['status'] ?? ''))
            ?? QueueWorkerStatus::Failed;

        $worker->forceFill([
            'name' => $snapshot['name'] ?? $worker->name,
            'connection' => $snapshot['connection'] ?? $worker->connection,
            'queue' => $snapshot['queue'] ?? $worker->queue,
            'php_version' => $snapshot['php_version'] ?? $worker->php_version,
            'processes' => (int) ($snapshot['processes'] ?? $worker->processes),
            'sleep' => (int) ($snapshot['sleep'] ?? $worker->sleep),
            'timeout' => (int) ($snapshot['timeout'] ?? $worker->timeout),
            'tries' => (int) ($snapshot['tries'] ?? $worker->tries),
            'backoff' => (int) ($snapshot['backoff'] ?? $worker->backoff),
            'max_jobs' => (int) ($snapshot['max_jobs'] ?? $worker->max_jobs),
            'max_time' => (int) ($snapshot['max_time'] ?? $worker->max_time),
            'stopwaitsecs' => (int) ($snapshot['stopwaitsecs'] ?? $worker->stopwaitsecs),
            'restart_on_deploy' => (bool) ($snapshot['restart_on_deploy'] ?? $worker->restart_on_deploy),
            'status' => $status,
            'failure_message' => $message,
        ])->save();
    }

    private function worker(Operation $operation): ?QueueWorker
    {
        $workerId = data_get($operation->plan_snapshot, 'queue_worker_id');

        if (! is_int($workerId) && ! is_numeric($workerId)) {
            return null;
        }

        return QueueWorker::query()->find((int) $workerId);
    }
}
