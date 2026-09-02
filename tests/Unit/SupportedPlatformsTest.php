<?php

use App\Models\Server;
use App\Support\SupportedPlatforms;
use Tests\TestCase;

uses(TestCase::class);

test('oracle linux 9.8 exposes remi php versions', function () {
    expect(SupportedPlatforms::phpVersions('ol', '9.8'))->toBe(['8.1', '8.2', '8.3', '8.4'])
        ->and(SupportedPlatforms::phpVersions('ol', '9'))->toBe(['8.1', '8.2', '8.3', '8.4'])
        ->and(SupportedPlatforms::supports('ol', '9.8'))->toBeTrue()
        ->and(SupportedPlatforms::defaultPhpVersion('ol', '9.8'))->toBe('8.4');
});

test('ubuntu php catalogs are unchanged', function () {
    expect(SupportedPlatforms::phpVersions('ubuntu', '24.04'))->toBe(['8.1', '8.2', '8.3', '8.4'])
        ->and(SupportedPlatforms::phpVersions('ubuntu', '26.04'))->toBe(['8.5']);
});

test('unknown platforms have an empty php catalog', function () {
    expect(SupportedPlatforms::phpVersions('ol', '8'))->toBe([])
        ->and(SupportedPlatforms::phpVersions('fedora', '40'))->toBe([]);
});

test('the supported label names oracle linux', function () {
    expect(SupportedPlatforms::supportedLabel())
        ->toContain('Ubuntu')
        ->toContain('Debian')
        ->toContain('Oracle Linux 9')
        ->and(SupportedPlatforms::osDisplayName('ol'))->toBe('Oracle Linux');
});

test('the php hint for oracle linux lists remi versions', function () {
    $server = new Server([
        'server_info' => [
            'os' => 'ol',
            'os_version' => '9.8',
        ],
    ]);

    expect(SupportedPlatforms::hintFor($server))
        ->toBe('Available on Oracle Linux 9.8: 8.1, 8.2, 8.3, 8.4.');
});

test('the php hint for an unsupported distro explains the catalog', function () {
    $server = new Server([
        'server_info' => [
            'os' => 'fedora',
            'os_version' => '40',
        ],
    ]);

    expect(SupportedPlatforms::hintFor($server))
        ->toStartWith('PHP provisioning is not available on Fedora 40.')
        ->toContain('Oracle Linux 9');
});

test('php versions for a connected oracle linux server match the catalog', function () {
    $server = new Server([
        'server_info' => [
            'os' => 'ol',
            'os_version' => '9.8',
        ],
    ]);

    expect(SupportedPlatforms::phpVersionsFor($server))->toBe(['8.1', '8.2', '8.3', '8.4']);
});
