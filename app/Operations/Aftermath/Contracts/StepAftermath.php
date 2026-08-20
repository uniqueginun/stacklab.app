<?php

namespace App\Operations\Aftermath\Contracts;

use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\Server;
use App\Support\StepExecutionResult;

interface StepAftermath
{
    /**
     * Stable key stored on operation_steps.aftermath.
     */
    public static function key(): string;

    public function handle(
        Server $server,
        Operation $operation,
        OperationStep $step,
        StepExecutionResult $result,
    ): void;
}
