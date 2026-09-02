<?php

use App\Models\QueueWorker;
use App\Support\QueueWorkers\QueueWorkerCommandBuilder;
use App\Support\QueueWorkers\QueueWorkerRecipe;
use App\Support\QueueWorkers\SupervisorQueueWorkerConfigBuilder;
use Tests\TestCase;

uses(TestCase::class);

test('name and restart on deploy do not count as supervisor config changes', function () {
    $worker = new QueueWorker([
        'connection' => 'redis',
        'queue' => 'default',
        'php_version' => '8.4',
        'processes' => 1,
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'stopwaitsecs' => 3600,
    ]);

    $recipe = new QueueWorkerRecipe(
        new QueueWorkerCommandBuilder,
        new SupervisorQueueWorkerConfigBuilder,
    );

    expect($recipe->configWouldChange($worker, [
        'name' => 'renamed',
        'connection' => 'redis',
        'queue' => 'default',
        'php_version' => '8.4',
        'processes' => 1,
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'stopwaitsecs' => 3600,
        'restart_on_deploy' => false,
    ]))->toBeFalse();
});

test('process count changes require a supervisor update', function () {
    $worker = new QueueWorker([
        'connection' => 'redis',
        'queue' => 'default',
        'php_version' => '8.4',
        'processes' => 1,
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'stopwaitsecs' => 3600,
    ]);

    $recipe = new QueueWorkerRecipe(
        new QueueWorkerCommandBuilder,
        new SupervisorQueueWorkerConfigBuilder,
    );

    expect($recipe->configWouldChange($worker, [
        'connection' => 'redis',
        'queue' => 'default',
        'php_version' => '8.4',
        'processes' => 3,
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'stopwaitsecs' => 3600,
    ]))->toBeTrue();
});
