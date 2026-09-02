<?php

namespace App\Models;

use App\Enums\QueueWorkerStatus;
use App\Support\QueueWorkers\SupervisorQueueWorkerConfigBuilder;
use Database\Factories\QueueWorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QueueWorker extends Model
{
    /** @use HasFactory<QueueWorkerFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'restart_on_deploy' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (QueueWorker $worker): void {
            $worker->uuid ??= (string) Str::uuid();
            $worker->status ??= QueueWorkerStatus::Pending;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QueueWorkerStatus::class,
            'processes' => 'integer',
            'sleep' => 'integer',
            'timeout' => 'integer',
            'tries' => 'integer',
            'backoff' => 'integer',
            'max_jobs' => 'integer',
            'max_time' => 'integer',
            'stopwaitsecs' => 'integer',
            'restart_on_deploy' => 'boolean',
            'installed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function supervisorProgram(): string
    {
        return (new SupervisorQueueWorkerConfigBuilder)->programName((int) $this->site_id, (int) $this->id);
    }

    public function supervisorConfigPath(): string
    {
        return (new SupervisorQueueWorkerConfigBuilder)->configPath((int) $this->site_id, (int) $this->id);
    }

    public function stdoutLogPath(): string
    {
        $this->loadMissing('site');

        return (new SupervisorQueueWorkerConfigBuilder)->logPath(
            (string) $this->site->root_path,
            (int) $this->id,
        );
    }

    public function artisanPath(): string
    {
        $this->loadMissing('site');

        return $this->site->currentPath().'/artisan';
    }

    public function workingDirectory(): string
    {
        $this->loadMissing('site');

        return $this->site->currentPath();
    }

    public function isInstalled(): bool
    {
        return $this->status === QueueWorkerStatus::Installed;
    }

    public function isBusy(): bool
    {
        $status = $this->status instanceof QueueWorkerStatus
            ? $this->status
            : QueueWorkerStatus::from((string) $this->status);

        return $status->isBusy();
    }

    public function canUpdate(): bool
    {
        return in_array($this->status, [
            QueueWorkerStatus::Installed,
            QueueWorkerStatus::Failed,
        ], true);
    }

    public function canRestart(): bool
    {
        return $this->status === QueueWorkerStatus::Installed;
    }

    public function canDelete(): bool
    {
        return in_array($this->status, [
            QueueWorkerStatus::Installed,
            QueueWorkerStatus::Failed,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function operationTypes(): array
    {
        return [
            'install_queue_worker',
            'update_queue_worker',
            'restart_queue_worker',
            'graceful_restart_queue_worker',
            'delete_queue_worker',
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     connection: string,
     *     queue: string,
     *     php_version: string,
     *     processes: int,
     *     sleep: int,
     *     timeout: int,
     *     tries: int,
     *     backoff: int,
     *     max_jobs: int,
     *     max_time: int,
     *     stopwaitsecs: int,
     *     restart_on_deploy: bool,
     *     status: string,
     *     failure_message: string|null
     * }
     */
    public function snapshotAttributes(): array
    {
        $status = $this->status instanceof QueueWorkerStatus
            ? $this->status
            : QueueWorkerStatus::from((string) $this->status);

        return [
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
            'failure_message' => $this->failure_message,
        ];
    }
}
