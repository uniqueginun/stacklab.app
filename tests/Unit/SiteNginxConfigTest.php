<?php

use App\Models\Site;
use App\Support\SiteNginxConfig;
use Tests\TestCase;

uses(TestCase::class);

test('the nginx server block uses the current symlink and php-fpm socket', function () {
    $site = new Site([
        'domain' => 'stacklab.test',
        'root_path' => '/var/www/stacklab.test',
        'web_directory' => '/public',
        'php_version' => '8.3',
    ]);

    $config = (new SiteNginxConfig)->serverBlock(
        $site,
        (new SiteNginxConfig)->documentRoot($site, useCurrentSymlink: true),
    );

    expect($config)
        ->toContain('server_name stacklab.test')
        ->toContain('root /var/www/stacklab.test/current/public')
        ->toContain('fastcgi_pass unix:/run/php/php8.3-fpm.sock');
});

test('the nginx socket falls back to the default php version', function () {
    $site = new Site([
        'domain' => 'stacklab.test',
        'root_path' => '/var/www/stacklab.test',
        'web_directory' => '/public',
        'php_version' => null,
    ]);

    $config = (new SiteNginxConfig)->serverBlock($site);

    expect($config)->toContain('fastcgi_pass unix:/run/php/php8.4-fpm.sock');
});

test('the activate recipe restarts nginx and opens the release to www-data', function () {
    $activate = file_get_contents(resource_path('recipes/deploy.activate@v1.sh'));
    $lib = file_get_contents(resource_path('recipes/_lib.sh'));
    $nginx = file_get_contents(resource_path('recipes/nginx.install@v1.sh'));

    expect($activate)
        ->toContain('mini_forge_ensure_www_data_readable')
        ->toContain('mini_forge_reload_nginx')
        ->toContain('mini_forge_disable_default_nginx_site')
        ->and($lib)
        ->toContain('umask 022')
        ->toContain('systemctl restart nginx')
        ->toContain('chmod -R a+rX')
        ->toContain('ug+rwX')
        ->toContain('bootstrap/cache')
        ->toContain('chmod 700 "${root}/.ssh"')
        ->and($nginx)
        ->toContain('mini_forge_disable_default_nginx_site')
        ->toContain('mini_forge_reload_nginx');
});
