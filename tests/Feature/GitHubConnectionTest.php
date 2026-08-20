<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\GitHubConnection;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('guests cannot start a github connection', function () {
    $this->get(route('connections.provider.redirect', 'github'))
        ->assertRedirect(route('login'));
});

test('guests cannot complete a github connection', function () {
    $this->get(route('connections.provider.callback', 'github'))
        ->assertRedirect(route('login'));
});

test('guests cannot disconnect github', function () {
    $this->delete(route('connections.provider.destroy', 'github'))
        ->assertRedirect(route('login'));
});

test('unknown version control providers are not found', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.provider.redirect', 'gitlab'))
        ->assertNotFound();
});

test('an authenticated user is redirected to github', function () {
    Socialite::fake('github');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.provider.redirect', 'github'))
        ->assertRedirect('https://socialite.fake/github/authorize');
});

test('an inertia visit to github uses an external location redirect', function () {
    Socialite::fake('github');

    $user = User::factory()->create();

    $version = app(HandleInertiaRequests::class)->version(request());

    $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ])
        ->get(route('connections.provider.redirect', 'github'))
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', 'https://socialite.fake/github/authorize');
});

test('a safe return url is remembered before redirecting to github', function () {
    Socialite::fake('github');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.provider.redirect', [
            'provider' => 'github',
            'return' => '/sites/example',
        ]))
        ->assertRedirect();

    expect(session('url.intended'))->toBe('/sites/example');
});

test('an external return url is not remembered', function () {
    Socialite::fake('github');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.provider.redirect', [
            'provider' => 'github',
            'return' => 'https://evil.test/phish',
        ]))
        ->assertRedirect();

    expect(session('url.intended'))->not->toBe('https://evil.test/phish');
});

test('an authenticated user can connect github', function () {
    Socialite::fake('github', SocialiteUser::fake([
        'id' => '123456',
        'nickname' => 'octocat',
        'name' => 'The Octocat',
        'token' => 'gho_test_token',
    ]));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.provider.callback', 'github'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'GitHub connected.',
        ]);

    $connection = $user->fresh()->githubConnection;

    expect($connection)->not->toBeNull()
        ->and($connection->github_id)->toBe('123456')
        ->and($connection->username)->toBe('octocat')
        ->and($connection->token)->toBe('gho_test_token');
});

test('connecting github updates an existing connection', function () {
    Socialite::fake('github', SocialiteUser::fake([
        'id' => '999',
        'nickname' => 'new-octocat',
        'token' => 'gho_refreshed',
    ]));

    $user = User::factory()->create();
    GitHubConnection::factory()->for($user)->create([
        'github_id' => '111',
        'username' => 'old-octocat',
    ]);

    $this->actingAs($user)
        ->get(route('connections.provider.callback', 'github'))
        ->assertRedirect(route('dashboard'));

    expect(GitHubConnection::query()->count())->toBe(1)
        ->and($user->fresh()->githubConnection)
        ->github_id->toBe('999')
        ->username->toBe('new-octocat')
        ->token->toBe('gho_refreshed');
});

test('the github callback redirects to the intended url', function () {
    Socialite::fake('github', SocialiteUser::fake([
        'id' => '123',
        'nickname' => 'octocat',
        'token' => 'gho_test_token',
    ]));

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();

    $this->actingAs($user)
        ->withSession(['url.intended' => route('sites.show', $site, false)])
        ->get(route('connections.provider.callback', 'github'))
        ->assertRedirect(route('sites.show', $site));
});

test('a failed github callback flashes an error', function () {
    Socialite::fake('github', function () {
        throw new RuntimeException('GitHub denied the request.');
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.provider.callback', 'github'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Unable to connect GitHub. Please try again.',
        ]);

    expect($user->fresh()->githubConnection)->toBeNull();
});

test('an authenticated user can disconnect github', function () {
    Http::fake();

    $user = User::factory()->create();
    $connection = GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->delete(route('connections.provider.destroy', 'github'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'GitHub disconnected.',
        ]);

    $this->assertModelMissing($connection);
});

test('disconnecting github is a no-op when no connection exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->delete(route('connections.provider.destroy', 'github'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'GitHub disconnected.',
        ]);
});

test('users can create and delete their own github connection', function () {
    $user = User::factory()->create();
    $connection = GitHubConnection::factory()->for($user)->create();

    expect($user->can('create', GitHubConnection::class))->toBeTrue()
        ->and($user->can('delete', $connection))->toBeTrue();
});

test('users cannot delete another users github connection', function () {
    $user = User::factory()->create();
    $connection = GitHubConnection::factory()->create();

    expect($user->can('delete', $connection))->toBeFalse();
});

test('the site detail page includes the github connection', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();
    GitHubConnection::factory()->for($user)->create(['username' => 'octocat']);

    $this->actingAs($user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('github.connected', true)
            ->where('github.username', 'octocat')
        );
});
