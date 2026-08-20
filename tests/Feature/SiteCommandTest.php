<?php

use App\Enums\SiteStatus;
use App\Models\Release;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;

function commandSite(?User $user = null, array $attributes = [], bool $withRelease = false): array
{
    $user ??= User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create([
        'domain' => 'stacklab.app',
        'root_path' => '/home/forge/stacklab.app',
        'php_version' => '8.4',
        ...$attributes,
    ]);

    if ($withRelease) {
        $release = Release::factory()->for($site)->for($user)->active()->create();
        $site->forceFill(['current_release_id' => $release->id])->save();
    }

    return [$user, $server, $site];
}

function commandOutput(string $output, int $exitCode = 0): string
{
    return "CMD_EXIT:{$exitCode}\nCMD_OUTPUT_B64_BEGIN\n".base64_encode($output)."\nCMD_OUTPUT_B64_END\n";
}

test('guests cannot run site commands', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.commands.store', $site), [
        'command' => 'php artisan config:clear',
    ])->assertRedirect(route('login'));
});

test('a user cannot run commands on another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => 'php artisan config:clear',
        ])
        ->assertForbidden();
});

test('command routes are bound by uuid', function () {
    [$user, $server, $site] = commandSite();

    $this->actingAs($user)
        ->postJson('/sites/'.$site->id.'/commands', [
            'command' => 'php artisan config:clear',
        ])
        ->assertNotFound();
});

test('php sites cannot run commands', function () {
    [$user, $server, $site] = commandSite(attributes: [
        'type' => 'PHP',
        'status' => SiteStatus::DEPLOYED,
    ], withRelease: true);

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => 'php artisan config:clear',
        ])
        ->assertNotFound();
});

test('html sites cannot run commands', function () {
    [$user, $server, $site] = commandSite(attributes: [
        'type' => 'HTML',
        'status' => SiteStatus::DEPLOYED,
    ], withRelease: true);

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => 'ls',
        ])
        ->assertNotFound();
});

test('an undeployed site cannot run commands', function () {
    [$user, $server, $site] = commandSite();

    $this->mock(SshService::class)->shouldNotReceive('run');

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => 'php artisan config:clear',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['site']);
});

test('a deployed site without a release cannot run commands', function () {
    [$user, $server, $site] = commandSite(attributes: [
        'status' => SiteStatus::DEPLOYED,
    ]);

    $this->mock(SshService::class)->shouldNotReceive('run');

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => 'php artisan config:clear',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['site']);
});

test('the owner can run a command on a deployed laravel site', function () {
    [$user, $server, $site] = commandSite(attributes: [
        'status' => SiteStatus::DEPLOYED,
    ], withRelease: true);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, commandOutput("Configuration cache cleared!\n")));

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => 'php artisan config:clear',
        ])
        ->assertOk()
        ->assertJsonPath('command', 'php artisan config:clear')
        ->assertJsonPath('working_directory', '/home/forge/stacklab.app/current')
        ->assertJsonPath('exit_code', 0)
        ->assertJsonPath('output', "Configuration cache cleared!\n");
});

test('a command is required', function () {
    [$user, $server, $site] = commandSite(attributes: [
        'status' => SiteStatus::DEPLOYED,
    ], withRelease: true);

    $this->actingAs($user)
        ->postJson(route('sites.commands.store', $site), [
            'command' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['command']);
});
