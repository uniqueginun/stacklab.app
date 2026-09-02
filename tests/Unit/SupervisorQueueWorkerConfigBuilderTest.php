<?php

use App\Support\QueueWorkers\SupervisorQueueWorkerConfigBuilder;
use Tests\TestCase;

uses(TestCase::class);

test('it generates a deterministic supervisor program name and path from database ids', function () {
    $builder = new SupervisorQueueWorkerConfigBuilder;

    expect($builder->programName(8, 42))->toBe('stacklab-site-8-worker-42')
        ->and($builder->configPath(8, 42))->toBe('/etc/supervisor/conf.d/stacklab-site-8-worker-42.conf')
        ->and($builder->logPath('/home/stacklab/stacklab.sh', 42))
        ->toBe('/home/stacklab/stacklab.sh/shared/logs/worker-42.log');
});

test('it generates a supervisor program using numprocs instead of multiple files', function () {
    $config = (new SupervisorQueueWorkerConfigBuilder)->build([
        'program' => 'stacklab-site-8-worker-42',
        'command' => "'/usr/bin/php8.5' '/home/stacklab/stacklab.sh/current/artisan' 'queue:work' 'redis' '--queue=emails'",
        'directory' => '/home/stacklab/stacklab.sh/current',
        'user' => 'stacklab',
        'processes' => 3,
        'stopwaitsecs' => 3600,
        'stdout_logfile' => '/home/stacklab/stacklab.sh/shared/logs/worker-42.log',
    ]);

    expect($config)
        ->toContain('[program:stacklab-site-8-worker-42]')
        ->toContain('process_name=%(program_name)s_%(process_num)02d')
        ->toContain('numprocs=3')
        ->toContain('user=stacklab')
        ->toContain('directory=/home/stacklab/stacklab.sh/current')
        ->toContain('stopwaitsecs=3600')
        ->toContain('stdout_logfile=/home/stacklab/stacklab.sh/shared/logs/worker-42.log')
        ->not->toContain('command=php artisan');
});

test('it rejects unsafe supervisor configuration values', function (array $override) {
    $options = [
        'program' => 'stacklab-site-8-worker-42',
        'command' => "'/usr/bin/php8.5' '/home/forge/app/current/artisan' 'queue:work' 'redis'",
        'directory' => '/home/forge/app/current',
        'user' => 'forge',
        'processes' => 1,
        'stopwaitsecs' => 3600,
        'stdout_logfile' => '/home/forge/app/shared/logs/worker-42.log',
        ...$override,
    ];

    expect(fn () => (new SupervisorQueueWorkerConfigBuilder)->build($options))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'display name program' => [['program' => 'emails-worker']],
    'newline command' => [['command' => "php artisan\nqueue:work"]],
    'ini comment user' => [['user' => 'forge;root']],
    'relative directory' => [['directory' => 'current']],
    'too many processes' => [['processes' => 99]],
]);

test('the install recipe applies a generated supervisor file and verifies the process group', function () {
    $recipe = file_get_contents(resource_path('recipes/queue_worker.install@v1.sh'));

    expect($recipe)
        ->toContain('supervisorctl reread')
        ->toContain('supervisorctl update')
        ->toContain('supervisorctl status')
        ->toContain('MF_SUPERVISOR_CONFIG_B64')
        ->not->toContain('php artisan queue:work');
});
