<?php

use App\Jobs\ProcessOperation;
use App\Models\Server;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use App\Support\RecipeRunner;
use App\Support\StepAftermathRegistry;

test('a successful operation sets the server profile', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'plan_snapshot' => ['profile' => 'static'],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install Nginx',
        'recipe' => 'nginx.install@v1',
        'status' => 'pending',
        'arguments' => [],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'nginx.install@v1',
            'success' => true,
            'changed' => true,
            'data' => [],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(app(RecipeRunner::class), app(StepAftermathRegistry::class));

    $operation->refresh();
    $server->refresh();

    expect($operation->status)->toBe('succeeded')
        ->and($operation->steps()->first()->status)->toBe('succeeded')
        ->and($server->profile)->toBe('static');
});

test('a failed step marks the operation failed without setting a profile', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'plan_snapshot' => ['profile' => 'php'],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install PHP',
        'recipe' => 'php.install@v1',
        'status' => 'pending',
        'arguments' => ['php_version' => '8.4'],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'php.install@v1',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'php_packages_unavailable', 'message' => 'PHP packages were not found.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(app(RecipeRunner::class), app(StepAftermathRegistry::class));

    $operation->refresh();
    $server->refresh();
    $step = $operation->steps()->first();

    expect($operation->status)->toBe('failed')
        ->and($operation->failure_message)->toBe('PHP packages were not found.')
        ->and($step->status)->toBe('failed')
        ->and($step->errorMessage())->toBe('PHP packages were not found.')
        ->and($server->profile)->toBeNull();
});

test('a runner exception fails the current step and operation', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'plan_snapshot' => ['profile' => 'static'],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install Nginx',
        'recipe' => 'missing.recipe@v1',
        'status' => 'pending',
        'arguments' => [],
    ]);

    (new ProcessOperation($operation->id))->handle(app(RecipeRunner::class), app(StepAftermathRegistry::class));

    $operation->refresh();
    $server->refresh();

    expect($operation->status)->toBe('failed')
        ->and($operation->failure_message)->toBe('Recipe [missing.recipe@v1] was not found.')
        ->and($operation->steps()->first()->status)->toBe('failed')
        ->and($server->profile)->toBeNull();
});

test('recipe output is persisted while the process is still running', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'deploy',
        'plan_snapshot' => ['site_id' => 1],
    ]);
    $step = $operation->steps()->create([
        'position' => 1,
        'name' => 'Build release',
        'recipe' => 'deploy.build@v1',
        'status' => 'pending',
        'arguments' => [],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturnUsing(function ($server, $host, $script, $timeout = 600, $onOutput = null) use ($step) {
            expect($onOutput)->toBeCallable();
            $onOutput("Loading composer repositories with package information\n");
            expect($step->fresh()->output)->toContain('Loading composer repositories');

            return new SshResult(0, json_encode([
                'step_key' => 'deploy.build@v1',
                'success' => true,
                'changed' => true,
                'data' => [],
                'error' => ['code' => null, 'message' => null, 'details' => null],
            ]));
        });

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($step->fresh()->status)->toBe('succeeded')
        ->and($step->fresh()->output)->toContain('Loading composer repositories');
});
