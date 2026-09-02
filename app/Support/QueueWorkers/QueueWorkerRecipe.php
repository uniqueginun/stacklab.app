<?php

namespace App\Support\QueueWorkers;

use App\Models\QueueWorker;

final class QueueWorkerRecipe
{
    /**
     * @var list<string>
     */
    public const array ConfigFields = [
        'connection',
        'queue',
        'php_version',
        'processes',
        'sleep',
        'timeout',
        'tries',
        'backoff',
        'max_jobs',
        'max_time',
        'stopwaitsecs',
    ];

    public function __construct(
        private QueueWorkerCommandBuilder $commands,
        private SupervisorQueueWorkerConfigBuilder $supervisor,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function configWouldChange(QueueWorker $worker, array $attributes): bool
    {
        foreach (self::ConfigFields as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            if ($this->normalized($worker->{$field}) !== $this->normalized($attributes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, scalar>
     */
    public function arguments(QueueWorker $worker): array
    {
        $worker->loadMissing('site.server');

        $command = $this->commands->build([
            'connection' => $worker->connection,
            'queue' => $worker->queue,
            'php_version' => $worker->php_version,
            'sleep' => (int) $worker->sleep,
            'timeout' => (int) $worker->timeout,
            'tries' => (int) $worker->tries,
            'backoff' => (int) $worker->backoff,
            'max_jobs' => (int) $worker->max_jobs,
            'max_time' => (int) $worker->max_time,
            'artisan_path' => $worker->artisanPath(),
        ]);

        $config = $this->supervisor->build([
            'program' => $worker->supervisorProgram(),
            'command' => $command,
            'directory' => $worker->workingDirectory(),
            'user' => (string) $worker->site->server->ssh_user,
            'processes' => (int) $worker->processes,
            'stopwaitsecs' => (int) $worker->stopwaitsecs,
            'stdout_logfile' => $worker->stdoutLogPath(),
        ]);

        return [
            'root_path' => $worker->site->root_path,
            'php_version' => $worker->php_version,
            'php_binary' => $this->commands->phpBinaryPath($worker->php_version),
            'artisan_path' => $worker->artisanPath(),
            'site_user' => $worker->site->server->ssh_user,
            'processes' => (string) $worker->processes,
            'supervisor_program' => $worker->supervisorProgram(),
            'supervisor_config_path' => $worker->supervisorConfigPath(),
            'supervisor_config_b64' => base64_encode($config),
            'worker_log_path' => $worker->stdoutLogPath(),
            'queue_worker_id' => $worker->id,
        ];
    }

    private function normalized(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
