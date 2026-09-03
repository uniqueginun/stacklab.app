<?php

use Tests\TestCase;

uses(TestCase::class);

test('preflight accepts oracle linux 9', function () {
    $preflight = file_get_contents(resource_path('recipes/preflight.check@v1.sh'));

    expect($preflight)
        ->toContain('"$os_id" == "ol"')
        ->toContain('Oracle Linux 9')
        ->toContain('yum.oracle.com')
        ->toContain('rpms.remirepo.net')
        ->toContain('dnf_locked');
});

test('the recipe library installs remi php on rhel and sury packages on debian', function () {
    $library = file_get_contents(resource_path('recipes/_lib.sh'));

    expect($library)
        ->toContain('php${version}-redis')
        ->toContain('php${short}-php-pecl-redis6')
        ->toContain('ol${major}_developer_EPEL')
        ->toContain('liblzf')
        ->toContain('mini_forge_enable_remi')
        ->toContain('rpms.remirepo.net')
        ->toContain('mini_forge_pkg_install')
        ->toContain('dnf install -y')
        ->toContain('listen = ${socket}')
        ->toContain('/run/php/php${version}-fpm.sock')
        ->toContain('user = www-data')
        ->toContain('include /etc/nginx/sites-enabled/*')
        ->toContain('httpd_enable_homedirs')
        ->toContain('/etc/supervisor/conf.d/*.conf')
        ->toContain('mini_forge_ensure_mysql_socket_auth')
        ->toContain('skip-grant-tables')
        ->toContain('connect-expired-password')
        ->toContain('/etc/mini-forge/mysql.cnf')
        ->toContain('--no-defaults')
        ->toContain('--socket=')
        ->toContain('--datadir=');
});

test('php and nginx install recipes dispatch through os-family helpers', function () {
    $php = file_get_contents(resource_path('recipes/php.install@v1.sh'));
    $nginx = file_get_contents(resource_path('recipes/nginx.install@v1.sh'));
    $mysql = file_get_contents(resource_path('recipes/mysql.install@v1.sh'));
    $node = file_get_contents(resource_path('recipes/node.install@v1.sh'));
    $activate = file_get_contents(resource_path('recipes/deploy.activate@v1.sh'));
    $database = file_get_contents(resource_path('recipes/database.create@v1.sh'));

    expect($php)
        ->toContain('mini_forge_pkg_installed')
        ->toContain('mini_forge_configure_php_fpm')
        ->toContain('mini_forge_link_php_cli')
        ->toContain('mini_forge_php_fpm_unit')
        ->and($nginx)
        ->toContain('mini_forge_ensure_nginx_layout')
        ->and($mysql)
        ->toContain('install_mysql_el')
        ->toContain('mysqld')
        ->toContain('mysql84-community-release-el')
        ->toContain('mini_forge_ensure_mysql_socket_auth')
        ->and($node)
        ->toContain('nodejs:22')
        ->toContain('mini_forge_pkg_installed')
        ->and($activate)
        ->toContain('mini_forge_reload_php_fpm')
        ->toContain('mini_forge_ensure_nginx_layout')
        ->and($database)
        ->toContain('mini_forge_ensure_mysql_socket_auth')
        ->toContain('mini_forge_mysql_exec');
});
