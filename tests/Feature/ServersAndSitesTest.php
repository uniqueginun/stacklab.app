<?php

use App\Enums\ConnectionStatus;
use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;
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
            ->where('tab', 'overview')
            ->where('server.has_mysql', false)
            ->has('sites', 0)
            ->has('databases', 0)
        );
});

test('the server detail page lists sites on that server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create(['domain' => 'stacklab.app']);
    Site::factory()->create(['domain' => 'other.test']);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/Show')
            ->has('sites', 1)
            ->where('sites.0.uuid', $site->uuid)
            ->where('sites.0.domain', 'stacklab.app')
            ->where('sites.0.status', SiteStatus::PENDING->value)
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
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Index')
            ->has('sites', 0)
        );
});

test('the sites index only lists the authenticated users sites', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create(['domain' => 'mine.test']);
    Site::factory()->create(['domain' => 'theirs.test']);

    $this->actingAs($user)
        ->get(route('sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Index')
            ->has('sites', 1)
            ->where('sites.0.uuid', $site->uuid)
            ->where('sites.0.domain', 'mine.test')
        );
});

test('the create site page can be rendered without a site type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Create')
            ->where('type', null)
            ->where('server', null)
            ->has('servers', 0)
        );
});

test('the create site page accepts an optional site type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.create', ['type' => 'PHP']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Create')
            ->where('type', 'PHP')
        );
});

test('the create site page lists only the users provisioned servers', function () {
    $user = User::factory()->create();
    $provisioned = Server::factory()->for($user)->provisioned()->create(['name' => 'mine']);
    Server::factory()->for($user)->connected()->create(['name' => 'unprovisioned']);
    Server::factory()->for($user)->create(['name' => 'unverified']);
    Server::factory()->provisioned()->create(['name' => 'theirs']);

    $this->actingAs($user)
        ->get(route('sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Create')
            ->has('servers', 1)
            ->where('servers.0.uuid', $provisioned->uuid)
            ->where('servers.0.name', 'mine')
            ->where('servers.0.host', $provisioned->host)
        );
});

test('the create site page defaults the server from the query string', function () {
    $user = User::factory()->create();
    Server::factory()->for($user)->provisioned()->create();
    $selected = Server::factory()->for($user)->provisioned()->create();

    $this->actingAs($user)
        ->get(route('sites.create', ['server' => $selected->uuid, 'type' => 'Laravel']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Create')
            ->where('server', $selected->uuid)
            ->where('type', 'Laravel')
            ->has('servers', 2)
        );
});

test('the create site page lists a provisioned debian server without php version options', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned('php', [
        'os' => 'debian',
        'os_version' => '13',
        'os_pretty' => 'Debian GNU/Linux 13',
    ])->create();

    $this->actingAs($user)
        ->get(route('sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Create')
            ->has('servers', 1)
            ->where('servers.0.uuid', $server->uuid)
            ->where('servers.0.os_label', 'Debian GNU/Linux 13')
            ->missing('servers.0.php_versions')
        );
});

test('a site cannot be stored with invalid data', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('sites.create'))
        ->post(route('sites.store'), [])
        ->assertRedirect(route('sites.create'))
        ->assertSessionHasErrors(['server', 'type', 'domain', 'web_directory']);
});

test('a user can create a site on a provisioned server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();

    $response = $this->actingAs($user)
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'Laravel',
            'domain' => 'stacklab.app',
            'web_directory' => '/public',
        ]);

    $site = Site::query()->first();

    expect($site)->not->toBeNull()
        ->and($site->user_id)->toBe($user->id)
        ->and($site->server_id)->toBe($server->id)
        ->and($site->domain)->toBe('stacklab.app')
        ->and($site->type)->toBe('Laravel')
        ->and($site->web_directory)->toBe('/public')
        ->and($site->root_path)->toBe('/var/www/stacklab.app')
        ->and($site->status)->toBe(SiteStatus::PENDING)
        ->and($site->php_version)->toBeNull();

    $response->assertRedirect(route('sites.show', $site));
});

test('a site copies the provisioned php version from the server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'succeeded',
        'plan_snapshot' => ['profile' => 'php', 'php_version' => '8.3'],
    ]);

    $this->actingAs($user)
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'Laravel',
            'domain' => 'php-copy.test',
            'web_directory' => '/public',
        ])
        ->assertRedirect();

    $site = Site::query()->where('domain', 'php-copy.test')->first();

    expect($site)->not->toBeNull()
        ->and($site->php_version)->toBe('8.3');
});

test('a site can be created without php or database version fields', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'HTML',
            'domain' => 'static.test',
            'web_directory' => '/',
        ])
        ->assertRedirect();

    $site = Site::query()->where('domain', 'static.test')->first();

    expect($site)->not->toBeNull()
        ->and($site->type)->toBe('HTML')
        ->and($site->web_directory)->toBe('/')
        ->and($site->root_path)->toBe('/var/www/static.test');
});

test('a site on a non-root ssh user is stored under the users home directory', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create([
        'ssh_user' => 'stacklab',
    ]);

    $this->actingAs($user)
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'Laravel',
            'domain' => 'stacklab.test',
            'web_directory' => '/public',
        ])
        ->assertRedirect();

    $site = Site::query()->where('domain', 'stacklab.test')->first();

    expect($site)->not->toBeNull()
        ->and($site->root_path)->toBe('/home/stacklab/stacklab.test');
});

test('a site cannot reuse a domain that already exists on the same server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    Site::factory()->for($user)->for($server)->create(['domain' => 'stacklab.app']);

    $this->actingAs($user)
        ->from(route('sites.create'))
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'Laravel',
            'domain' => 'stacklab.app',
            'web_directory' => '/public',
        ])
        ->assertRedirect(route('sites.create'))
        ->assertSessionHasErrors(['domain']);
});

test('a site cannot be stored on another users server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->connected()->create();

    $this->actingAs($user)
        ->from(route('sites.create'))
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'HTML',
            'domain' => 'stacklab.app',
            'web_directory' => '/public',
        ])
        ->assertRedirect(route('sites.create'))
        ->assertSessionHasErrors(['server']);
});

test('a site cannot be stored on an unprovisioned server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->connected()->create();

    $this->actingAs($user)
        ->from(route('sites.create'))
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'HTML',
            'domain' => 'stacklab.app',
            'web_directory' => '/public',
        ])
        ->assertRedirect(route('sites.create'))
        ->assertSessionHasErrors(['server']);
});

test('a site cannot be stored on an unconnected server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('sites.create'))
        ->post(route('sites.store'), [
            'server' => $server->uuid,
            'type' => 'HTML',
            'domain' => 'stacklab.app',
            'web_directory' => '/public',
        ])
        ->assertRedirect(route('sites.create'))
        ->assertSessionHasErrors(['server']);
});

test('guests cannot store a site', function () {
    $this->post(route('sites.store'), [
        'type' => 'Laravel',
        'domain' => 'stacklab.app',
        'web_directory' => '/public',
    ])->assertRedirect(route('login'));
});

test('the site detail page can be rendered', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create(['domain' => 'stacklab.app']);

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'info')
            ->where('site.uuid', $site->uuid)
            ->where('site.domain', 'stacklab.app')
            ->where('site.status', SiteStatus::PENDING->value)
            ->where('site.server.uuid', $server->uuid)
            ->where('site.root_path', $site->root_path)
            ->where('site.web_directory', $site->web_directory)
            ->where('site.php_version', null)
            ->where('site.current_release', null)
            ->where('site.is_laravel', true)
            ->where('site.is_php', true)
            ->where('github.connected', false)
            ->where('github.username', null)
        );
});

test('the site deployments tab can be rendered', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();

    $this->actingAs($user)
        ->get(route('sites.deployments', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'deployments')
            ->where('site.uuid', $site->uuid)
            ->where('github.connected', false)
            ->where('operation', null)
            ->where('releases', [])
            ->where('site.is_laravel', true)
        );
});

test('the site ssl tab can be rendered', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->deployed()->create();

    $this->actingAs($user)
        ->get(route('sites.ssl', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'ssl')
            ->where('site.uuid', $site->uuid)
            ->where('site.can_manage_ssl', true)
            ->where('certificate', null)
        );
});

test('the site source control tab can be rendered', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();

    $this->actingAs($user)
        ->get(route('sites.source', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'source')
            ->where('site.uuid', $site->uuid)
            ->where('github.connected', false)
        );
});

test('the site environment tab can be rendered for php sites', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->php()->create();

    $this->actingAs($user)
        ->get(route('sites.environment', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'environment')
            ->where('site.uuid', $site->uuid)
            ->where('site.is_php', true)
            ->where('site.is_laravel', false)
        );
});

test('the site environment tab can be rendered for laravel sites', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();

    $this->actingAs($user)
        ->get(route('sites.environment', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'environment')
            ->where('site.is_php', true)
            ->where('site.is_laravel', true)
        );
});

test('html sites cannot open the environment tab', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->html()->create();

    $this->actingAs($user)
        ->get(route('sites.environment', $site))
        ->assertNotFound();
});

test('the site commands tab can be rendered for laravel sites', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();

    $this->actingAs($user)
        ->get(route('sites.commands', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'commands')
            ->where('site.uuid', $site->uuid)
            ->where('site.is_laravel', true)
        );
});

test('php sites cannot open the commands tab', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->php()->create();

    $this->actingAs($user)
        ->get(route('sites.commands', $site))
        ->assertNotFound();
});

test('html sites cannot open the commands tab', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->html()->create();

    $this->actingAs($user)
        ->get(route('sites.commands', $site))
        ->assertNotFound();
});

test('a user cannot view another users site source control', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.source', $site))
        ->assertForbidden();
});

test('a user cannot view another users site environment', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.environment', $site))
        ->assertForbidden();
});

test('a user cannot view another users site commands', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.commands', $site))
        ->assertForbidden();
});

test('a user cannot view another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertForbidden();
});

test('a user cannot view another users site deployments', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.deployments', $site))
        ->assertForbidden();
});

test('guests cannot view a site', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.show', $site))
        ->assertRedirect(route('login'));
});

test('guests cannot view a site environment tab', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.environment', $site))
        ->assertRedirect(route('login'));
});

test('guests cannot view a site commands tab', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.commands', $site))
        ->assertRedirect(route('login'));
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
