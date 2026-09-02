<?php

use App\Support\QueueWorkers\QueueWorkerRuntimeStatusParser;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('it parses supervisor process states into structured runtime data', function (string $output, int $configured, int $running, bool $healthy, bool $missing) {
    $checkedAt = Carbon::parse('2026-09-01T20:25:00Z');
    $result = (new QueueWorkerRuntimeStatusParser)->parse($output, $configured, $checkedAt);

    expect($result['configured_processes'])->toBe($configured)
        ->and($result['running_processes'])->toBe($running)
        ->and($result['healthy'])->toBe($healthy)
        ->and($result['missing'])->toBe($missing)
        ->and($result['checked_at'])->toBe($checkedAt->toIso8601String());
})->with([
    'all running' => [
        "stacklab-site-8-worker-42_00   RUNNING   pid 11, uptime 0:01:00\nstacklab-site-8-worker-42_01   RUNNING   pid 12, uptime 0:01:00\nstacklab-site-8-worker-42_02   RUNNING   pid 13, uptime 0:01:00\n",
        3,
        3,
        true,
        false,
    ],
    'starting is not healthy' => [
        "stacklab-site-8-worker-42_00   STARTING\nstacklab-site-8-worker-42_01   RUNNING   pid 12, uptime 0:00:02\n",
        2,
        1,
        false,
        false,
    ],
    'stopped' => [
        "stacklab-site-8-worker-42_00   STOPPED   Not started\n",
        1,
        0,
        false,
        false,
    ],
    'fatal' => [
        "stacklab-site-8-worker-42_00   FATAL     Exited too quickly\n",
        1,
        0,
        false,
        false,
    ],
    'backoff' => [
        "stacklab-site-8-worker-42_00   BACKOFF   Exited too quickly\n",
        1,
        0,
        false,
        false,
    ],
    'exited' => [
        "stacklab-site-8-worker-42_00   EXITED    0\n",
        1,
        0,
        false,
        false,
    ],
    'missing program' => [
        'ERROR: no such process group',
        3,
        0,
        false,
        true,
    ],
]);

test('it parses grouped supervisor output for multiple programs', function () {
    $output = <<<'TXT'
STACKLAB_PROGRAM_BEGIN:stacklab-site-8-worker-42
stacklab-site-8-worker-42_00   RUNNING   pid 11, uptime 0:01:00
STACKLAB_PROGRAM_END:stacklab-site-8-worker-42
STACKLAB_PROGRAM_BEGIN:stacklab-site-8-worker-43
ERROR: no such process
STACKLAB_PROGRAM_END:stacklab-site-8-worker-43
TXT;

    $result = (new QueueWorkerRuntimeStatusParser)->parseGroups($output, [
        'stacklab-site-8-worker-42' => 1,
        'stacklab-site-8-worker-43' => 2,
    ], Carbon::parse('2026-09-01T20:25:00Z'));

    expect($result['stacklab-site-8-worker-42']['healthy'])->toBeTrue()
        ->and($result['stacklab-site-8-worker-42']['running_processes'])->toBe(1)
        ->and($result['stacklab-site-8-worker-43']['missing'])->toBeTrue()
        ->and($result['stacklab-site-8-worker-43']['healthy'])->toBeFalse();
});
