<?php

namespace App\Operations\Aftermath;

use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Operations\Aftermath\Contracts\HandlesFailedOperation;
use App\Operations\Aftermath\Contracts\StepAftermath;
use App\Support\StepExecutionResult;

final class FinalizeDatabaseAftermath implements HandlesFailedOperation, StepAftermath
{
    public static function key(): string
    {
        return 'finalize_database';
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

        $this->database($operation)?->forceFill([
            'status' => 'ready',
            'failure_message' => null,
        ])->save();
    }

    public function failed(Operation $operation, ?string $message): void
    {
        if ($operation->type !== 'create_database') {
            return;
        }

        $this->database($operation)?->forceFill([
            'status' => 'failed',
            'failure_message' => $message,
        ])->save();
    }

    private function database(Operation $operation): ?ServerDatabase
    {
        $databaseId = data_get($operation->plan_snapshot, 'database_id');

        if (! is_int($databaseId) && ! is_numeric($databaseId)) {
            return null;
        }

        return ServerDatabase::query()->find((int) $databaseId);
    }
}
