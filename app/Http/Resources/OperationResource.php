<?php

namespace App\Http\Resources;

use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Operation
 */
class OperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'status' => $this->status,
            'failure_message' => $this->failure_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'steps' => $this->steps
                ->sortBy('position')
                ->values()
                ->map(fn ($step): array => [
                    'id' => $step->id,
                    'position' => $step->position,
                    'name' => $step->name,
                    'recipe' => $step->recipe,
                    'status' => $step->status,
                    'error_message' => $step->errorMessage(),
                    'output' => $step->output,
                ])
                ->all(),
        ];
    }
}
