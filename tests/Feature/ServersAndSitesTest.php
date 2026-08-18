<?php

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the servers index can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('servers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Index')
            ->has('servers', 0)
        );
});

test('the servers index only lists the authenticated users servers', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create(['name' => 'mine']);
    Server::factory()->create(['name' => 'theirs']);

    $this->actingAs($user)
        ->get(route('servers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Index')
            ->has('servers', 1)
            ->where('servers.0.uuid', $server->uuid)
            ->where('servers.0.name', 'mine')
        );
});

test('the connect server page can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('servers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('servers/Create'));
});

test('a user can connect a server', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('servers.store'), [
            'name' => 'fragrant-forest',
            'provider' => 'digitalocean',
            'host' => '167.99.1.1',
            'ssh_port' => 22,
            'ssh_user' => 'root',
        ]);

    $server = Server::query()->first();

    expect($server)->not->toBeNull()
        ->and($server->name)->toBe('fragrant-forest')
        ->and($server->user_id)->toBe($user->id)
        ->and($server->connection_status)->toBe(ConnectionStatus::UNVERIFIED);

    $response->assertRedirect(route('servers.show', $server));
});

test('a server cannot be stored with invalid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('servers.create'))
        ->post(route('servers.store'), [])
        ->assertRedirect(route('servers.create'))
        ->assertSessionHasErrors(['name', 'provider', 'host', 'ssh_port', 'ssh_user']);
});

test('guests cannot store a server', function () {
    $this->post(route('servers.store'), [
        'name' => 'fragrant-forest',
        'provider' => 'digitalocean',
        'host' => '167.99.1.1',
        'ssh_port' => 22,
        'ssh_user' => 'root',
    ])->assertRedirect(route('login'));
});

test('the server detail page can be rendered', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->where('server.uuid', $server->uuid)
            ->where('server.name', $server->name)
            ->where('server.host', $server->host)
        );
});

test('a user cannot view another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertForbidden();
});

test('a user can delete their server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('servers.destroy', $server))
        ->assertRedirect(route('servers.index'));

    $this->assertDatabaseMissing('servers', ['id' => $server->id]);
});

test('a user cannot delete another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create();

    $this->actingAs($user)
        ->delete(route('servers.destroy', $server))
        ->assertForbidden();

    $this->assertDatabaseHas('servers', ['id' => $server->id]);
});

test('the sites index can be rendered', function () {
    $this->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/Index'));
});

test('the create site page can be rendered', function () {
    $this->get(route('sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/Create'));
});

test('the site detail page can be rendered', function () {
    $this->get(route('sites.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('sites/Show'));
});

test('authenticated users see the servers index on the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Index')
            ->has('servers')
        );
});
