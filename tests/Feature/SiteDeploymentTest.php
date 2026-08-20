<?php

use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\GitHubConnection;
use App\Models\Operation;
use App\Models\Release;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function deployableSite(?User $user = null): array
{
    $user ??= User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->withRepository()->create();
    GitHubConnection::factory()->for($user)->create();

    return [$user, $server, $site];
}

function fakeGithubHead(string $sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', string $message = 'Ship it'): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/*' => Http::response([
            'sha' => $sha,
            'commit' => ['message' => $message],
        ]),
    ]);
}

test('guests cannot start a deployment', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.deployments.store', $site))
        ->assertRedirect(route('login'));
});

test('a user cannot deploy another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->withRepository()->create();

    $this->actingAs($user)
        ->post(route('sites.deployments.store', $site))
        ->assertForbidden();
});

test('deployments are bound by site uuid', function () {
    [$user, $server, $site] = deployableSite();

    $this->actingAs($user)
        ->post('/sites/'.$site->id.'/deployments')
        ->assertNotFound();
});

test('a site cannot be deployed without a repository', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create();
    $site = Site::factory()->for($user)->for($server)->create();
    GitHubConnection::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('sites.deployments', $site))
        ->post(route('sites.deployments.store', $site))
        ->assertRedirect(route('sites.deployments', $site))
        ->assertSessionHasErrors(['site']);
});

test('the owner can start a deployment', function () {
    Queue::fake();
    fakeGithubHead();

    [$user, $server, $site] = deployableSite();

    $this->actingAs($user)
        ->post(route('sites.deployments.store', $site), [
            'run_composer' => true,
            'run_npm' => false,
            'run_migrations' => true,
            'run_caches' => true,
            'run_queue_restart' => false,
            'run_hook' => false,
        ])
        ->assertRedirect(route('sites.deployments', $site));

    $site->refresh();
    $operation = Operation::query()->first();
    $release = Release::query()->first();

    expect($site->status)->toBe(SiteStatus::DEPLOYING)
        ->and($site->deployment_options['run_npm'])->toBeFalse()
        ->and($operation)->not->toBeNull()
        ->and($operation->type)->toBe('deploy')
        ->and($operation->status)->toBe('pending')
        ->and($operation->server_id)->toBe($server->id)
        ->and($release)->not->toBeNull()
        ->and($release->commit_sha)->toBe('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
        ->and($release->status)->toBe('deploying')
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'deploy.clone@v1',
            'deploy.link_shared@v1',
            'deploy.build@v1',
            'deploy.hook@v1',
            'deploy.activate@v1',
            'deploy.verify@v1',
            'deploy.prune@v1',
        ]);

    $nginx = base64_decode((string) $operation->steps->first()->arguments['nginx_config_b64']);

    expect($nginx)
        ->toContain('/current/public')
        ->toContain('fastcgi_pass unix:/run/php/php8.4-fpm.sock')
        ->and($site->php_version)->toBe('8.4');

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('the deployments tab shows the latest operation and releases', function () {
    [$user, $server, $site] = deployableSite();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'deploy',
        'status' => 'running',
        'plan_snapshot' => ['site_id' => $site->id],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Build release',
        'recipe' => 'deploy.build@v1',
        'status' => 'running',
        'output' => "Loading composer repositories with package information\nInstalling dependencies",
    ]);
    $release = Release::factory()->for($site)->for($user)->deploying()->create([
        'operation_id' => $operation->id,
        'commit_sha' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        'commit_message' => 'Ship it',
    ]);

    $this->actingAs($user)
        ->get(route('sites.deployments', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'deployments')
            ->where('operation.uuid', $operation->uuid)
            ->where('operation.status', 'running')
            ->where('operation.steps.0.output', "Loading composer repositories with package information\nInstalling dependencies")
            ->has('releases', 1)
            ->where('releases.0.uuid', $release->uuid)
            ->where('releases.0.short_sha', 'bbbbbbb')
            ->where('releases.0.can_rollback', false)
        );
});

test('guests cannot start a rollback', function () {
    $site = Site::factory()->create();
    $release = Release::factory()->for($site)->active()->create();

    $this->post(route('sites.rollbacks.store', [$site, $release]))
        ->assertRedirect(route('login'));
});

test('a user cannot roll back another users release', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $release = Release::factory()->for($site)->active()->create();

    $this->actingAs($user)
        ->post(route('sites.rollbacks.store', [$site, $release]))
        ->assertForbidden();
});

test('a rollback is scoped to the site', function () {
    [$user, $server, $site] = deployableSite();
    $otherSite = Site::factory()->for($user)->for($server)->create();
    $release = Release::factory()->for($otherSite)->for($user)->active()->create();

    $this->actingAs($user)
        ->post(route('sites.rollbacks.store', [$site, $release]))
        ->assertNotFound();
});

test('the owner can start a rollback', function () {
    Queue::fake();

    [$user, $server, $site] = deployableSite();
    $previous = Release::factory()->for($site)->for($user)->rolledBack()->create([
        'commit_sha' => 'cccccccccccccccccccccccccccccccccccccccc',
    ]);
    $current = Release::factory()->for($site)->for($user)->active()->create();
    $site->forceFill([
        'current_release_id' => $current->id,
        'status' => SiteStatus::DEPLOYED,
    ])->save();

    $this->actingAs($user)
        ->post(route('sites.rollbacks.store', [$site, $previous]))
        ->assertRedirect(route('sites.deployments', $site));

    $site->refresh();
    $previous->refresh();
    $operation = Operation::query()->first();

    expect($site->status)->toBe(SiteStatus::DEPLOYING)
        ->and($previous->status)->toBe('deploying')
        ->and($operation)->not->toBeNull()
        ->and($operation->type)->toBe('rollback')
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'deploy.activate@v1',
            'deploy.verify@v1',
        ]);

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('the current release cannot be rolled back to itself', function () {
    [$user, $server, $site] = deployableSite();
    $current = Release::factory()->for($site)->for($user)->active()->create();
    $site->forceFill(['current_release_id' => $current->id])->save();

    $this->actingAs($user)
        ->from(route('sites.deployments', $site))
        ->post(route('sites.rollbacks.store', [$site, $current]))
        ->assertRedirect(route('sites.deployments', $site))
        ->assertSessionHasErrors(['release']);
});
