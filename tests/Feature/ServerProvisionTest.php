<?php

use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the server page exposes provisioning options for a connected server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('server.uuid', $server->uuid)
            ->where('server.is_connected', true)
            ->where('server.is_provisioned', false)
            ->where('server.can_provision', true)
            ->where('server.php_versions', ['8.1', '8.2', '8.3', '8.4'])
            ->where('server.mysql_versions', ['8.4', '8.0'])
            ->where('operation', null)
            ->has('profiles', 2)
            ->where('profiles.0.key', 'php')
            ->where('profiles.1.key', 'static')
        );
});

test('a connected user can start static provisioning', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'static',
        ])
        ->assertRedirect(route('servers.show', $server));

    $operation = Operation::query()->first();

    expect($operation)->not->toBeNull()
        ->and($operation->type)->toBe('provision')
        ->and($operation->status)->toBe('pending')
        ->and($operation->user_id)->toBe($user->id)
        ->and($operation->plan_snapshot['profile'])->toBe('static')
        ->and($operation->steps)->toHaveCount(3)
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'preflight.check@v1',
            'nginx.install@v1',
            'profile.verify@v1',
        ]);

    expect(Site::query()->count())->toBe(0);

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('a connected user can start php provisioning with versions', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'php',
            'php_version' => '8.3',
            'mysql_version' => '8.0',
        ])
        ->assertRedirect(route('servers.show', $server));

    $operation = Operation::query()->first();

    expect($operation)->not->toBeNull()
        ->and($operation->plan_snapshot['profile'])->toBe('php')
        ->and($operation->plan_snapshot['php_version'])->toBe('8.3')
        ->and($operation->plan_snapshot['mysql_version'])->toBe('8.0')
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'preflight.check@v1',
            'php.install@v1',
            'composer.install@v1',
            'nginx.install@v1',
            'mysql.install@v1',
            'redis.install@v1',
            'node.install@v1',
            'profile.verify@v1',
        ]);

    $phpStep = $operation->steps->firstWhere('recipe', 'php.install@v1');
    $mysqlStep = $operation->steps->firstWhere('recipe', 'mysql.install@v1');

    expect($phpStep->arguments['php_version'])->toBe('8.3')
        ->and($mysqlStep->arguments['mysql_version'])->toBe('8.0');

    expect(Site::query()->count())->toBe(0);

    Queue::assertPushed(ProcessOperation::class);
});

test('php provisioning requires php and mysql versions', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provision', $server), [
            'profile' => 'php',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHasErrors(['php_version', 'mysql_version']);
});

test('php provisioning rejects a php version incompatible with the server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provision', $server), [
            'profile' => 'php',
            'php_version' => '8.5',
            'mysql_version' => '8.4',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHasErrors(['php_version']);
});

test('static provisioning does not require php or mysql versions', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'static',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionDoesntHaveErrors(['php_version', 'mysql_version']);
});

test('an unconnected server cannot be provisioned', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'static',
        ])
        ->assertForbidden();
});

test('a user cannot provision another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();

    $this->actingAs($user)
        ->post(route('servers.provision', $server), [
            'profile' => 'static',
        ])
        ->assertForbidden();
});

test('an already provisioned server cannot be provisioned again', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();

    $this->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provision', $server), [
            'profile' => 'static',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHasErrors(['profile']);
});

test('a server with an active operation cannot start another provision', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => ['profile' => 'static'],
    ]);

    $this->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provision', $server), [
            'profile' => 'static',
        ])
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHasErrors(['server']);
});

test('the server page includes the current provision operation', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => ['profile' => 'static'],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install Nginx',
        'recipe' => 'nginx.install@v1',
        'status' => 'running',
        'arguments' => [],
    ]);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('operation.uuid', $operation->uuid)
            ->where('operation.status', 'running')
            ->has('operation.steps', 1)
            ->where('operation.steps.0.name', 'Install Nginx')
            ->where('operation.steps.0.status', 'running')
        );
});

test('guests cannot provision a server', function () {
    $server = Server::factory()->connected()->create();

    $this->post(route('servers.provision', $server), [
        'profile' => 'static',
    ])->assertRedirect(route('login'));
});
