<?php

namespace App\Jobs;

use App\Models\Operation;
use App\Support\RecipeRunner;
use App\Support\StepAftermathRegistry;
use App\Support\StepExecutionResult;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

#[Timeout(1800)]
#[UniqueFor(1900)]
class ProcessOperation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $operationId) {}

    public function uniqueId(): string
    {
        return (string) $this->operationId;
    }

    /**
     * Execute the job.
     */
    public function handle(RecipeRunner $runner, StepAftermathRegistry $aftermaths): void
    {
        $operation = Operation::query()->with(['server', 'steps'])->findOrFail(
            $this->operationId
        );

        if (! in_array($operation->status, ['pending', 'running'], true)) {
            return;
        }

        $operation->start();

        $profile = $operation->getProfile();

        $steps = $operation->steps()
            ->orderBy('position')
            ->get()
            ->filter(fn ($step) => $step->status !== 'succeeded');

        foreach ($steps as $step) {
            $step->run();

            try {
                $result = $runner->run($operation->server, $step);
            } catch (Throwable $e) {
                $step->fail($e->getMessage());
                $operation->fail($e->getMessage());
                $aftermaths->applyFailure($operation, $e->getMessage());

                return;
            }

            $exeResult = StepExecutionResult::fromArray($result);

            $step->markFinished($exeResult);

            if (! $exeResult->success) {
                $operation->fail($exeResult->errorMessage());
                $aftermaths->applyFailure($operation, $exeResult->errorMessage());

                return;
            }

            $aftermaths->apply($operation->server, $operation, $step, $exeResult);
        }

        $operation->succeed();

        if ($profile !== null) {
            $operation->server->forceFill([
                'profile' => $profile,
            ])->save();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $operation = Operation::query()->find($this->operationId);

        if ($operation === null || in_array($operation->status, ['succeeded', 'failed'], true)) {
            return;
        }

        $message = $exception?->getMessage() ?? 'The provisioning operation failed.';

        $operation->fail($message);

        app(StepAftermathRegistry::class)->applyFailure($operation, $message);
    }
}
