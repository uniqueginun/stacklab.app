<?php

use Tests\TestCase;

uses(TestCase::class);

test('the update recipe restores the previous supervisor config on failure', function () {
    $recipe = file_get_contents(resource_path('recipes/queue_worker.update@v1.sh'));

    expect($recipe)
        ->toContain('restore_previous_config')
        ->toContain('sudo -n cp "${backup}" "${MF_SUPERVISOR_CONFIG_PATH}"')
        ->toContain('mini_forge_assert_queue_worker_config_path')
        ->toContain('mini_forge_supervisor_wait_running');
});

test('the delete recipe only removes the managed supervisor program path', function () {
    $recipe = file_get_contents(resource_path('recipes/queue_worker.delete@v1.sh'));

    expect($recipe)
        ->toContain('mini_forge_assert_queue_worker_config_path')
        ->toContain('sudo -n rm -f "${MF_SUPERVISOR_CONFIG_PATH}"')
        ->not->toContain('rm -rf /etc/supervisor/conf.d')
        ->not->toContain('stacklab-site-*-worker-*.conf');
});

test('the restart recipe targets the exact supervisor program', function () {
    $recipe = file_get_contents(resource_path('recipes/queue_worker.restart@v1.sh'));

    expect($recipe)
        ->toContain('supervisorctl restart "${MF_SUPERVISOR_PROGRAM}:*"')
        ->toContain('mini_forge_assert_queue_worker_config_path');
});

test('the graceful restart recipe signals laravel from the current release', function () {
    $recipe = file_get_contents(resource_path('recipes/queue_worker.graceful_restart@v1.sh'));

    expect($recipe)
        ->toContain('queue:restart --no-ansi --no-interaction')
        ->toContain('cd "${CURRENT}"')
        ->toContain('CURRENT="${MF_ROOT_PATH}/current"')
        ->toContain('mini_forge_ensure_www_data_readable')
        ->toContain('"${MF_PHP_BINARY}" "${MF_ARTISAN_PATH}"')
        ->toContain('mini_forge_ensure_php_redis')
        ->not->toContain('supervisorctl');
});

test('php provision includes the redis extension and can install it on demand', function () {
    $library = file_get_contents(resource_path('recipes/_lib.sh'));
    $install = file_get_contents(resource_path('recipes/queue_worker.install@v1.sh'));
    $update = file_get_contents(resource_path('recipes/queue_worker.update@v1.sh'));

    expect($library)
        ->toContain('php${version}-redis')
        ->toContain('mini_forge_ensure_php_redis')
        ->toContain('extension_loaded("redis")')
        ->and($install)
        ->toContain('mini_forge_ensure_php_redis')
        ->and($update)
        ->toContain('mini_forge_ensure_php_redis');
});
