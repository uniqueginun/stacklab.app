<?php

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

test('mini_forge_is_laravel matches stored site types case-insensitively', function (string $type, bool $expected) {
    $library = resource_path('recipes/_lib.sh');

    $result = Process::env(['MF_SITE_TYPE' => $type])->run([
        'bash',
        '-c',
        'source "$1"; trap - ERR; if mini_forge_is_laravel; then echo yes; else echo no; fi',
        'bash',
        $library,
    ]);

    expect($result->errorOutput())->toBeEmpty()
        ->and($result->successful())->toBeTrue()
        ->and(trim($result->output()))->toBe($expected ? 'yes' : 'no');
})->with([
    'Laravel' => ['Laravel', true],
    'laravel' => ['laravel', true],
    'LARAVEL' => ['LARAVEL', true],
    'PHP' => ['PHP', false],
    'HTML' => ['HTML', false],
]);

test('deploy recipes treat Laravel sites as laravel and refuse an unlinked storage tree', function () {
    $lib = file_get_contents(resource_path('recipes/_lib.sh'));
    $linkShared = file_get_contents(resource_path('recipes/deploy.link_shared@v1.sh'));
    $build = file_get_contents(resource_path('recipes/deploy.build@v1.sh'));
    $activate = file_get_contents(resource_path('recipes/deploy.activate@v1.sh'));

    expect($lib)
        ->toContain('mini_forge_is_laravel')
        ->toContain('"${type,,}"')
        ->toContain('readlink -f "${release}/storage"')
        ->and($linkShared)
        ->toContain('mini_forge_is_laravel')
        ->not->toContain('== "laravel"')
        ->toContain('storage_not_linked')
        ->and($build)
        ->toContain('mini_forge_is_laravel')
        ->not->toContain('== "laravel"')
        ->and($activate)
        ->toContain('mini_forge_is_laravel')
        ->not->toContain('== "laravel"');
});
