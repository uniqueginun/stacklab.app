<?php

use App\Jobs\ProcessOperation;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use App\Support\RecipeRunner;
use App\Support\StepAftermathRegistry;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view server databases', function () {
    $server = Server::factory()->provisioned()->create();

    $this->get(route('servers.databases', $server))
        ->assertRedirect(route('login'));
});

test('the owner can view the databases tab', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $database = ServerDatabase::factory()->for($user)->for($server)->ready()->create([
        'name' => 'app_production',
        'username' => 'app_production',
        'password' => 'copy-me-later',
    ]);

    $this->actingAs($user)
        ->get(route('servers.databases', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('tab', 'databases')
            ->where('server.has_mysql', true)
            ->has('databases', 1)
            ->where('databases.0.uuid', $database->uuid)
            ->where('databases.0.name', 'app_production')
            ->where('databases.0.username', 'app_production')
            ->where('databases.0.password', 'copy-me-later')
            ->where('databases.0.status', 'ready')
        );
});

test('a user cannot view another users databases', function () {
    $user = User::factory()->create();
    $server = Server::factory()->provisioned()->create();

    $this->actingAs($user)
        ->get(route('servers.databases', $server))
        ->assertForbidden();
});

test('the owner can create a database', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();

    $this->actingAs($user)
        ->post(route('servers.databases.store', $server), [
            'name' => 'app_production',
        ])
        ->assertRedirect(route('servers.databases', $server));

    $database = ServerDatabase::query()->first();
    $operation = $server->operations()->where('type', 'create_database')->first();

    expect($database)->not->toBeNull()
        ->and($database->name)->toBe('app_production')
        ->and($database->username)->toBe('app_production')
        ->and($database->status)->toBe('pending')
        ->and($database->password)->not->toBeEmpty()
        ->and($operation)->not->toBeNull()
        ->and($operation->steps->pluck('recipe')->all())->toBe(['database.create@v1'])
        ->and($operation->steps->first()->arguments)->not->toHaveKey('db_password')
        ->and($operation->steps->first()->arguments['database_id'])->toBe($database->id);

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('creating a database rejects reserved names', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();

    $this->actingAs($user)
        ->from(route('servers.databases', $server))
        ->post(route('servers.databases.store', $server), [
            'name' => 'mysql',
        ])
        ->assertRedirect(route('servers.databases', $server))
        ->assertSessionHasErrors(['name']);
});

test('database names must be unique on the server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    ServerDatabase::factory()->for($user)->for($server)->create(['name' => 'app_production']);

    $this->actingAs($user)
        ->from(route('servers.databases', $server))
        ->post(route('servers.databases.store', $server), [
            'name' => 'app_production',
        ])
        ->assertRedirect(route('servers.databases', $server))
        ->assertSessionHasErrors(['name']);
});

test('a static server cannot create a database', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned('static')->create();

    $this->actingAs($user)
        ->post(route('servers.databases.store', $server), [
            'name' => 'app_production',
        ])
        ->assertForbidden();
});

test('a user cannot create a database on another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->provisioned()->create();

    $this->actingAs($user)
        ->post(route('servers.databases.store', $server), [
            'name' => 'app_production',
        ])
        ->assertForbidden();
});

test('a successful database operation marks the database ready', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $database = ServerDatabase::factory()->for($user)->for($server)->create([
        'password' => 'injected-secret',
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'create_database',
        'plan_snapshot' => [
            'database_id' => $database->id,
            'database_uuid' => $database->uuid,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Create database',
        'recipe' => 'database.create@v1',
        'aftermath' => 'finalize_database',
        'status' => 'pending',
        'arguments' => [
            'db_name' => $database->name,
            'db_username' => $database->username,
            'database_id' => $database->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->withArgs(function ($sshServer, $host, string $script): bool {
            expect($script)->toContain("export MF_DB_PASSWORD='injected-secret'")
                ->and($script)->toContain('database.create');

            return true;
        })
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'database.create',
            'success' => true,
            'changed' => true,
            'data' => ['name' => $database->name, 'username' => $database->username],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($database->fresh()->status)->toBe('ready')
        ->and($operation->fresh()->status)->toBe('succeeded');
});

test('a failed database operation stores the failure message', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $database = ServerDatabase::factory()->for($user)->for($server)->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'create_database',
        'plan_snapshot' => [
            'database_id' => $database->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Create database',
        'recipe' => 'database.create@v1',
        'aftermath' => 'finalize_database',
        'status' => 'pending',
        'arguments' => [
            'db_name' => $database->name,
            'db_username' => $database->username,
            'database_id' => $database->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'database.create',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'mysql_unavailable', 'message' => 'MySQL/MariaDB admin access failed.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($database->fresh()->status)->toBe('failed')
        ->and($database->fresh()->failure_message)->toBe('MySQL/MariaDB admin access failed.')
        ->and($operation->fresh()->status)->toBe('failed');
});
