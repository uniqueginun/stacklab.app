<?php

use App\Support\ProcessOutputBuffer;

test('it appends complete lines as they arrive', function () {
    $persisted = [];
    $buffer = new ProcessOutputBuffer(function (string $output) use (&$persisted): void {
        $persisted[] = $output;
    }, throttleMilliseconds: 0);

    $buffer->ingest("Loading composer repositories with package information\n");
    $buffer->ingest("Updating dependencies\nInstalling dependencies from lock file\n");

    expect($buffer->output())->toBe(
        "Loading composer repositories with package information\nUpdating dependencies\nInstalling dependencies from lock file"
    )
        ->and($persisted[0])->toContain('Loading composer repositories');
});

test('it replaces in-progress composer progress bars', function () {
    $buffer = new ProcessOutputBuffer(function (string $output): void {}, throttleMilliseconds: 0);

    $buffer->ingest("  - Installing laravel/framework (v12.0.0): Downloading (21%)\r");
    expect($buffer->output())->toBe('- Installing laravel/framework (v12.0.0): Downloading (21%)');

    $buffer->ingest("  - Installing laravel/framework (v12.0.0): Downloading (100%)\r");
    expect($buffer->output())->toBe('- Installing laravel/framework (v12.0.0): Downloading (100%)');

    $buffer->ingest("  - Installing laravel/framework (v12.0.0): Extracting archive\n");
    expect($buffer->output())->toBe('- Installing laravel/framework (v12.0.0): Extracting archive');
});

test('it flushes leftover output when the process finishes', function () {
    $last = '';
    $buffer = new ProcessOutputBuffer(function (string $output) use (&$last): void {
        $last = $output;
    }, throttleMilliseconds: 0);

    $buffer->ingest('Generating optimized autoload files');
    $buffer->finish();

    expect($last)->toBe('Generating optimized autoload files');
});
