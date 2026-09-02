<?php

namespace Database\Factories;

use App\Enums\QueueWorkerStatus;
use App\Models\QueueWorker;
use App\Models\Site;
use App\Support\QueueWorkers\QueueWorkerSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueWorker>
 */
class QueueWorkerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $defaults = QueueWorkerSettings::defaults();

        return [
            'site_id' => Site::factory(),
            'name' => 'worker-'.fake()->unique()->numerify('####'),
            'connection' => $defaults['connection'],
            'queue' => $defaults['queue'],
            'php_version' => '8.4',
            'processes' => $defaults['processes'],
            'sleep' => $defaults['sleep'],
            'timeout' => $defaults['timeout'],
            'tries' => $defaults['tries'],
            'backoff' => $defaults['backoff'],
            'max_jobs' => $defaults['max_jobs'],
            'max_time' => $defaults['max_time'],
            'stopwaitsecs' => $defaults['stopwaitsecs'],
            'restart_on_deploy' => $defaults['restart_on_deploy'],
            'status' => QueueWorkerStatus::Pending,
        ];
    }

    public function installing(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueWorkerStatus::Installing,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueWorkerStatus::Installed,
            'installed_at' => now(),
            'failure_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueWorkerStatus::Failed,
            'failure_message' => 'Unable to install the queue worker.',
        ]);
    }

    public function updating(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueWorkerStatus::Updating,
        ]);
    }

    public function restarting(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueWorkerStatus::Restarting,
        ]);
    }

    public function deleting(): static
    {
        return $this->state(fn (): array => [
            'status' => QueueWorkerStatus::Deleting,
        ]);
    }
}
