<?php

namespace App\Http\Resources;

use App\Enums\QueueWorkerStatus;
use App\Models\QueueWorker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QueueWorker
 */
class QueueWorkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof QueueWorkerStatus
            ? $this->status
            : QueueWorkerStatus::from((string) $this->status);

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'php_version' => $this->php_version,
            'processes' => (int) $this->processes,
            'sleep' => (int) $this->sleep,
            'timeout' => (int) $this->timeout,
            'tries' => (int) $this->tries,
            'backoff' => (int) $this->backoff,
            'max_jobs' => (int) $this->max_jobs,
            'max_time' => (int) $this->max_time,
            'stopwaitsecs' => (int) $this->stopwaitsecs,
            'restart_on_deploy' => (bool) $this->restart_on_deploy,
            'status' => $status->value,
            'status_label' => $status->label(),
            'failure_message' => $this->failure_message,
            'installed_at' => $this->installed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
