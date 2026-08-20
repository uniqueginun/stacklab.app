<?php

use App\Enums\SiteStatus;
use App\Models\GitHubConnection;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use Illuminate\Support\Facades\Http;

test('guests cannot view site repositories', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.repository.edit', $site))
        ->assertRedirect(route('login'));
});

test('guests cannot attach a site repository', function () {
    $site = Site::factory()->create();

    $this->put(route('sites.repository.update', $site), [
        'repository' => 'octocat/hello',
        'branch' => 'main',
    ])->assertRedirect(route('login'));
});

test('a user cannot list repositories for another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.repository.edit', $site))
        ->assertForbidden();
});

test('a user cannot attach a repository to another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.repository.update', $site), [
            'repository' => 'octocat/hello',
            'branch' => 'main',
        ])
        ->assertForbidden();
});

test('site repository routes are bound by uuid', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)
        ->get('/sites/'.$site->id.'/repository')
        ->assertNotFound();
});

test('the owner can list github repositories for a site', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            [
                'id' => 1,
                'full_name' => 'octocat/hello',
                'private' => false,
                'default_branch' => 'main',
            ],
        ]),
    ]);

    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('sites.repository.edit', $site))
        ->assertOk()
        ->assertJsonPath('githubConnected', true)
        ->assertJsonPath('repositories.0.full_name', 'octocat/hello')
        ->assertJsonPath('branches', []);
});

test('the owner can list branches for a selected repository', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            [
                'id' => 1,
                'full_name' => 'octocat/hello',
                'private' => false,
                'default_branch' => 'main',
            ],
        ]),
        'https://api.github.com/repos/octocat/hello/branches*' => Http::response([
            ['name' => 'main'],
            ['name' => 'develop'],
        ]),
    ]);

    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('sites.repository.edit', [
            'site' => $site,
            'repository' => 'octocat/hello',
        ]))
        ->assertOk()
        ->assertJsonPath('branches.0.name', 'main')
        ->assertJsonPath('branches.1.name', 'develop');
});

test('attaching a repository requires a repository and branch', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('sites.source', $site))
        ->put(route('sites.repository.update', $site), [])
        ->assertRedirect(route('sites.source', $site))
        ->assertSessionHasErrors(['repository', 'branch']);
});

function fakeSuccessfulGitHubAttachRequests(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([]),
        'https://api.github.com/repos/octocat/hello/branches*' => Http::response([
            ['name' => 'main'],
        ]),
        'https://api.github.com/repos/octocat/hello/keys' => Http::response([
            'id' => 42,
            'title' => 'mini-forge:stacklab.app',
            'key' => 'ssh-ed25519 AAAA',
            'fingerprint' => 'SHA256:deploy',
        ], 201),
        'https://api.github.com/repos/octocat/hello/keys/*' => Http::response(status: 204),
        'https://api.github.com/repos/octocat/hello' => Http::response([
            'id' => 99,
            'full_name' => 'octocat/hello',
            'private' => false,
            'default_branch' => 'main',
        ]),
    ]);
}

test('a user can attach a github repository to a site', function () {
    fakeSuccessfulGitHubAttachRequests();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create([
        'domain' => 'stacklab.app',
        'root_path' => '/home/forge/stacklab.app',
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, "DEPLOY_KEY_INSTALLED\n"));

    $this->actingAs($user)
        ->put(route('sites.repository.update', $site), [
            'repository' => 'octocat/hello',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.source', $site))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Repository attached.',
        ]);

    $site->refresh();

    expect($site->repository_url)->toBe('octocat/hello')
        ->and($site->repository_id)->toBe(99)
        ->and($site->repository_branch)->toBe('main')
        ->and($site->deploy_key_id)->toBe(42);
});

test('attaching a repository fills a missing site root path', function () {
    fakeSuccessfulGitHubAttachRequests();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create([
        'ssh_user' => 'stacklab',
    ]);
    $site = Site::factory()->for($user)->for($server)->create([
        'domain' => 'stacklab.test',
        'root_path' => null,
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->withArgs(function ($server, $host, string $script): bool {
            expect($script)->toContain('/home/stacklab/stacklab.test')
                ->and($script)->toContain('sudo -n tee')
                ->and($script)->not->toContain('dirname');

            return true;
        })
        ->andReturn(new SshResult(0, "DEPLOY_KEY_INSTALLED\n"));

    $this->actingAs($user)
        ->put(route('sites.repository.update', $site), [
            'repository' => 'octocat/hello',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.source', $site));

    expect($site->refresh()->root_path)->toBe('/home/stacklab/stacklab.test');
});

test('a failed deploy key install includes the ssh error', function () {
    fakeSuccessfulGitHubAttachRequests();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create([
        'domain' => 'stacklab.app',
        'root_path' => '/var/www/stacklab.app',
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, '', 'chown: cannot access \'\': No such file or directory'));

    $this->actingAs($user)
        ->from(route('sites.source', $site))
        ->put(route('sites.repository.update', $site), [
            'repository' => 'octocat/hello',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.source', $site))
        ->assertSessionHasErrors([
            'repository' => 'Unable to install the deploy key on the server: chown: cannot access \'\': No such file or directory',
        ]);
});

test('a repository cannot be attached without a github connection', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();

    $this->actingAs($user)
        ->from(route('sites.source', $site))
        ->put(route('sites.repository.update', $site), [
            'repository' => 'octocat/hello',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.source', $site))
        ->assertSessionHasErrors(['repository']);
});

test('a failed site cannot attach a repository', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create([
        'status' => SiteStatus::FAILED,
    ]);
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('sites.source', $site))
        ->put(route('sites.repository.update', $site), [
            'repository' => 'octocat/hello',
            'branch' => 'main',
        ])
        ->assertRedirect(route('sites.source', $site))
        ->assertSessionHasErrors(['repository']);
});

test('users can view and update their own sites', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    expect($user->can('view', $site))->toBeTrue()
        ->and($user->can('update', $site))->toBeTrue()
        ->and($user->can('delete', $site))->toBeTrue();
});

test('users cannot update another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    expect($user->can('view', $site))->toBeFalse()
        ->and($user->can('update', $site))->toBeFalse()
        ->and($user->can('delete', $site))->toBeFalse();
});
