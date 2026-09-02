<?php

use App\Enums\SiteCertificateStatus;
use App\Enums\SiteCertificateType;
use App\Models\Site;
use App\Models\SiteCertificate;
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
        ->toContain('fastcgi_pass unix:/run/php/php8.3-fpm.sock')
        ->toContain('/.well-known/acme-challenge/')
        ->toContain('root /var/www/letsencrypt')
        ->not->toContain('listen 443');
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

test('an active certificate adds a 443 block and http redirect', function () {
    $site = new Site([
        'domain' => 'stacklab.test',
        'root_path' => '/var/www/stacklab.test',
        'web_directory' => '/public',
        'php_version' => '8.4',
    ]);
    $certificate = new SiteCertificate([
        'type' => SiteCertificateType::LETS_ENCRYPT,
        'status' => SiteCertificateStatus::ACTIVE,
        'domains' => ['stacklab.test', 'www.stacklab.test'],
    ]);
    $certificate->setRelation('site', $site);

    $config = (new SiteNginxConfig)->serverBlock(
        $site,
        (new SiteNginxConfig)->documentRoot($site, useCurrentSymlink: true),
        $certificate,
    );

    expect($config)
        ->toContain('listen 443 ssl http2')
        ->toContain('server_name stacklab.test www.stacklab.test')
        ->toContain('ssl_certificate /etc/letsencrypt/live/stacklab.test/fullchain.pem')
        ->toContain('ssl_certificate_key /etc/letsencrypt/live/stacklab.test/privkey.pem')
        ->toContain('return 301 https://$host$request_uri')
        ->toContain('Strict-Transport-Security')
        ->toContain('/.well-known/acme-challenge/');
});

test('existing certificates use the nginx ssl directory', function () {
    $site = new Site([
        'domain' => 'stacklab.test',
        'root_path' => '/var/www/stacklab.test',
        'web_directory' => '/public',
        'php_version' => '8.4',
    ]);
    $certificate = new SiteCertificate([
        'type' => SiteCertificateType::EXISTING,
        'status' => SiteCertificateStatus::ACTIVE,
        'domains' => ['stacklab.test'],
    ]);
    $certificate->setRelation('site', $site);

    $config = (new SiteNginxConfig)->serverBlock($site, certificate: $certificate);

    expect($config)
        ->toContain('ssl_certificate /etc/nginx/ssl/stacklab.test/fullchain.pem')
        ->toContain('ssl_certificate_key /etc/nginx/ssl/stacklab.test/privkey.pem');
});

test('the activate recipe restarts nginx and opens the release to www-data', function () {
    $activate = file_get_contents(resource_path('recipes/deploy.activate@v1.sh'));
    $lib = file_get_contents(resource_path('recipes/_lib.sh'));
    $nginx = file_get_contents(resource_path('recipes/nginx.install@v1.sh'));

    expect($activate)
        ->toContain('mini_forge_ensure_www_data_readable')
        ->toContain('mini_forge_reload_nginx')
        ->toContain('mini_forge_ensure_nginx_layout')
        ->and($lib)
        ->toContain('umask 022')
        ->toContain('systemctl restart nginx')
        ->toContain('chmod -R a+rX')
        ->toContain('ug+rwX')
        ->toContain('bootstrap/cache')
        ->toContain('chmod 700 "${root}/.ssh"')
        ->toContain('mini_forge_write_nginx_vhost')
        ->toContain('mini_forge_set_app_url_scheme')
        ->and($nginx)
        ->toContain('mini_forge_ensure_nginx_layout')
        ->toContain('mini_forge_reload_nginx');
});
