<?php

use App\Support\QueueWorkers\QueueWorkerLogRedactor;
use Tests\TestCase;

uses(TestCase::class);

test('it redacts password secret token and app key values', function () {
    $output = implode("\n", [
        'password=hunter2',
        'SECRET: s3cret',
        'token = abcdef',
        'APP_KEY=base64:abcdefghijklmnopqrstuvwxyz012345',
        'AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        'job processed',
    ]);

    $redacted = (new QueueWorkerLogRedactor)->redact($output);

    expect($redacted)
        ->toContain('password=[REDACTED]')
        ->toContain('SECRET=[REDACTED]')
        ->toContain('token=[REDACTED]')
        ->toContain('APP_KEY=[REDACTED]')
        ->toContain('AWS_SECRET_ACCESS_KEY=[REDACTED]')
        ->toContain('job processed')
        ->not->toContain('hunter2')
        ->not->toContain('s3cret')
        ->not->toContain('abcdef')
        ->not->toContain('abcdefghijklmnopqrstuvwxyz012345')
        ->not->toContain('wJalrXUtnFEMI');
});

test('it redacts aws access key ids', function () {
    $redacted = (new QueueWorkerLogRedactor)->redact('Using key AKIAIOSFODNN7EXAMPLE for S3');

    expect($redacted)
        ->toContain('[REDACTED]')
        ->not->toContain('AKIAIOSFODNN7EXAMPLE');
});
