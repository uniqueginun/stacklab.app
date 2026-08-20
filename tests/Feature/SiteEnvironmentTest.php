<?php

use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;

function environmentSite(?User $user = null, array $attributes = []): array
{
    $user ??= User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create([
        'domain' => 'stacklab.app',
        'root_path' => '/home/forge/stacklab.app',
        'php_version' => '8.4',
        ...$attributes,
    ]);

    return [$user, $server, $site];
}

function environmentFileOutput(string $contents): string
{
    return "ENV_B64_BEGIN\n".base64_encode($contents)."\nENV_B64_END\n";
}

test('guests cannot view the environment file', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.environment.edit', $site))
        ->assertRedirect(route('login'));
});

test('guests cannot update the environment file', function () {
    $site = Site::factory()->create();

    $this->put(route('sites.environment.update', $site), [
        'contents' => 'APP_KEY=test',
    ])->assertRedirect(route('login'));
});

test('a user cannot view another users environment file', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.environment.edit', $site))
        ->assertForbidden();
});

test('a user cannot update another users environment file', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.environment.update', $site), [
            'contents' => 'APP_KEY=test',
        ])
        ->assertForbidden();
});

test('environment routes are bound by uuid', function () {
    [$user, $server, $site] = environmentSite();

    $this->actingAs($user)
        ->get('/sites/'.$site->id.'/environment/file')
        ->assertNotFound();
});

test('html sites cannot read the environment file', function () {
    [$user, $server, $site] = environmentSite(attributes: ['type' => 'HTML']);

    $this->actingAs($user)
        ->get(route('sites.environment.edit', $site))
        ->assertNotFound();
});

test('an undeployed site returns a null environment file', function () {
    [$user, $server, $site] = environmentSite();

    $this->mock(SshService::class)->shouldNotReceive('run');

    $this->actingAs($user)
        ->get(route('sites.environment.edit', $site))
        ->assertOk()
        ->assertJsonPath('contents', null)
        ->assertJsonPath('path', '/home/forge/stacklab.app/shared/.env');
});

test('the owner can read a deployed environment file', function () {
    [$user, $server, $site] = environmentSite(attributes: [
        'status' => SiteStatus::DEPLOYED,
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, environmentFileOutput("APP_KEY=base\n")));

    $this->actingAs($user)
        ->get(route('sites.environment.edit', $site))
        ->assertOk()
        ->assertJsonPath('contents', "APP_KEY=base\n")
        ->assertJsonPath('path', '/home/forge/stacklab.app/shared/.env');
});

test('an undeployed site cannot be updated', function () {
    [$user, $server, $site] = environmentSite();

    $this->mock(SshService::class)->shouldNotReceive('run');

    $this->actingAs($user)
        ->from(route('sites.environment', $site))
        ->put(route('sites.environment.update', $site), [
            'contents' => "APP_KEY=secret\n",
        ])
        ->assertRedirect(route('sites.environment', $site))
        ->assertSessionHasErrors(['site']);
});

test('the owner can update a deployed environment file', function () {
    [$user, $server, $site] = environmentSite(attributes: [
        'status' => SiteStatus::DEPLOYED,
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, "ENV_UPDATED\n"));

    $this->actingAs($user)
        ->from(route('sites.environment', $site))
        ->put(route('sites.environment.update', $site), [
            'contents' => "APP_KEY=secret\n",
        ])
        ->assertRedirect(route('sites.environment', $site))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Environment file saved.',
        ]);
});
