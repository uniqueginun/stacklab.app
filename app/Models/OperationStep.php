<?php

namespace App\Models;

use App\Support\StepExecutionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationStep extends Model
{
    public const OUTPUT_BYTE_LIMIT = 32_000;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Operation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function run(): void
    {
        $this->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'finished_at' => null,
        ])->save();
    }

    public function fail(string $message): void
    {
        $this->forceFill([
            'status' => 'failed',
            'output' => $this->clipOutput($message),
            'result' => [
                'step_key' => $this->recipe,
                'success' => false,
                'changed' => false,
                'data' => [],
                'error' => [
                    'code' => 'runner_exception',
                    'message' => mb_substr($message, 0, 2000),
                    'details' => null,
                ],
            ],
            'finished_at' => now(),
        ])->save();
    }

    public function resetForRetry(): void
    {
        $this->forceFill([
            'status' => 'pending',
            'output' => null,
            'result' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();
    }

    public function markFinished(StepExecutionResult $result): void
    {
        $this->forceFill([
            'status' => $result->getResultStatus(),
            'result' => [
                'step_key' => $result->stepKey,
                'success' => $result->success,
                'changed' => $result->changed,
                'data' => $result->data,
                'error' => $result->error,
            ],
            'output' => $this->clipOutput($result->output),
            'finished_at' => now(),
        ])->save();
    }

    public function persistOutput(string $output): void
    {
        $this->forceFill([
            'output' => $this->clipOutput($output),
        ])->save();
    }

    public function errorMessage(): ?string
    {
        $message = data_get($this->result, 'error.message');

        if (! is_string($message) || $message === '') {
            return null;
        }

        return $message;
    }

    private function clipOutput(?string $output): ?string
    {
        if ($output === null || $output === '') {
            return $output;
        }

        if (strlen($output) <= self::OUTPUT_BYTE_LIMIT) {
            return $output;
        }

        return mb_strcut($output, -self::OUTPUT_BYTE_LIMIT);
    }
}
