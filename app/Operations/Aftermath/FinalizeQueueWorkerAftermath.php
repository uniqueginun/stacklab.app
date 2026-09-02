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

        $this->worker($operation)?->forceFill([
            'status' => QueueWorkerStatus::Installed,
            'failure_message' => null,
            'installed_at' => now(),
        ])->save();
    }

    public function failed(Operation $operation, ?string $message): void
    {
        if ($operation->type !== 'install_queue_worker') {
            return;
        }

        $this->worker($operation)?->forceFill([
            'status' => QueueWorkerStatus::Failed,
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
