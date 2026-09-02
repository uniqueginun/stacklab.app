<?php

use App\Support\QueueWorkers\QueueWorkerCommandBuilder;
use Tests\TestCase;

uses(TestCase::class);

test('it generates a deterministic artisan command from required fields', function () {
    $command = (new QueueWorkerCommandBuilder)->build([
        'connection' => 'redis',
        'queue' => 'emails',
        'php_version' => '8.5',
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'artisan_path' => '/home/stacklab/stacklab.sh/current/artisan',
    ]);

    expect($command)->toBe(implode(' ', array_map(escapeshellarg(...), [
        '/usr/bin/php8.5',
        '/home/stacklab/stacklab.sh/current/artisan',
        'queue:work',
        'redis',
        '--queue=emails',
        '--sleep=3',
        '--tries=3',
        '--timeout=90',
    ])));
});

test('it includes optional flags only when they are meaningful', function () {
    $command = (new QueueWorkerCommandBuilder)->build([
        'connection' => 'database',
        'queue' => 'emails,notifications',
        'php_version' => '8.4',
        'sleep' => 5,
        'timeout' => 120,
        'tries' => 2,
        'backoff' => 10,
        'max_jobs' => 100,
        'max_time' => 3600,
        'artisan_path' => '/home/forge/app.com/current/artisan',
    ]);

    expect($command)
        ->toContain(escapeshellarg('--backoff=10'))
        ->toContain(escapeshellarg('--max-jobs=100'))
        ->toContain(escapeshellarg('--max-time=3600'))
        ->toContain(escapeshellarg('--queue=emails,notifications'))
        ->not->toContain('--once');
});

test('it rejects unsafe connection, queue, php version, and artisan path values', function (array $override) {
    $options = [
        'connection' => 'redis',
        'queue' => 'default',
        'php_version' => '8.4',
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'artisan_path' => '/home/forge/app.com/current/artisan',
        ...$override,
    ];

    expect(fn () => (new QueueWorkerCommandBuilder)->build($options))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'shell connection' => [['connection' => 'redis; rm -rf /']],
    'newline queue' => [['queue' => "default\nmalicious"]],
    'relative artisan' => [['artisan_path' => 'artisan']],
    'path traversal artisan' => [['artisan_path' => '/home/forge/../etc/passwd']],
    'invalid php' => [['php_version' => '8.4-extra']],
]);
