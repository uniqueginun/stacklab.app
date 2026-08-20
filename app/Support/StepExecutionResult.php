<?php

namespace App\Support;

final readonly class StepExecutionResult
{
    /**
     * @param  array{code: string|null, message: string|null, details: mixed}  $error
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $stepKey,
        public bool $success,
        public bool $changed,
        public array $data,
        public array $error = [
            'code' => null,
            'message' => null,
            'details' => null,
        ],
        public ?string $output = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $error = data_get($data, 'error');

        if (! is_array($error)) {
            $error = [
                'code' => null,
                'message' => is_string($error) ? $error : null,
                'details' => null,
            ];
        }

        return new self(
            stepKey: (string) data_get($data, 'step_key', 'unknown'),
            success: (bool) data_get($data, 'success', false),
            changed: (bool) data_get($data, 'changed', false),
            data: is_array(data_get($data, 'data')) ? data_get($data, 'data') : [],
            error: [
                'code' => is_string($error['code'] ?? null) ? $error['code'] : null,
                'message' => is_string($error['message'] ?? null) ? $error['message'] : null,
                'details' => $error['details'] ?? null,
            ],
            output: is_string(data_get($data, 'output')) ? data_get($data, 'output') : null,
        );
    }

    public function getResultStatus(): string
    {
        return $this->success ? 'succeeded' : 'failed';
    }

    public function errorMessage(): string
    {
        return $this->error['message'] ?? 'The operation step failed.';
    }
}
