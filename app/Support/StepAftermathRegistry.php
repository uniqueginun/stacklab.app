<?php

namespace App\Support;

use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\Server;
use App\Operations\Aftermath\Contracts\HandlesFailedOperation;
use App\Operations\Aftermath\Contracts\StepAftermath;
use Illuminate\Support\Collection;
use RuntimeException;

final class StepAftermathRegistry
{
    /** @var Collection<string, StepAftermath> */
    private Collection $handlers;

    /**
     * @param  iterable<StepAftermath>  $handlers
     */
    public function __construct(iterable $handlers)
    {
        $this->handlers = collect($handlers)->keyBy(
            fn (StepAftermath $handler): string => $handler::key(),
        );
    }

    public function apply(
        Server $server,
        Operation $operation,
        OperationStep $step,
        StepExecutionResult $result,
    ): void {
        if (! filled($step->aftermath)) {
            return;
        }

        $handler = $this->handlers->get($step->aftermath);

        if ($handler === null) {
            throw new RuntimeException("No step aftermath handler registered for [{$step->aftermath}].");
        }

        $handler->handle($server, $operation, $step, $result);
    }

    public function applyFailure(Operation $operation, ?string $message): void
    {
        $seen = [];

        foreach ($operation->steps()->orderBy('position')->get() as $step) {
            if (! filled($step->aftermath) || isset($seen[$step->aftermath])) {
                continue;
            }

            $handler = $this->handlers->get($step->aftermath);

            if (! $handler instanceof HandlesFailedOperation) {
                continue;
            }

            $seen[$step->aftermath] = true;
            $handler->failed($operation, $message);
        }
    }
}
