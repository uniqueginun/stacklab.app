<?php

use App\Models\Site;

test('laravel sites are php sites', function () {
    $site = new Site(['type' => 'Laravel']);

    expect($site->isLaravel())->toBeTrue()
        ->and($site->isPhp())->toBeTrue();
});

test('php sites are not laravel sites', function () {
    $site = new Site(['type' => 'PHP']);

    expect($site->isPhp())->toBeTrue()
        ->and($site->isLaravel())->toBeFalse();
});

test('html sites are not php sites', function () {
    $site = new Site(['type' => 'HTML']);

    expect($site->isPhp())->toBeFalse()
        ->and($site->isLaravel())->toBeFalse();
});

test('it builds remote environment and current paths', function () {
    $site = new Site([
        'root_path' => '/home/forge/stacklab.app',
        'php_version' => '8.4',
    ]);

    expect($site->environmentFilePath())->toBe('/home/forge/stacklab.app/shared/.env')
        ->and($site->currentPath())->toBe('/home/forge/stacklab.app/current')
        ->and($site->phpBinary())->toBe('php8.4');
});

test('it falls back to the generic php binary', function () {
    $site = new Site(['php_version' => null]);

    expect($site->phpBinary())->toBe('php');
});
