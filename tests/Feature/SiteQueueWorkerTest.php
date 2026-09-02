<?php

use App\Enums\QueueWorkerStatus;
use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\QueueWorker;
use App\Models\Release;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use App\Support\QueueWorkers\QueueWorkerCommandBuilder;
use App\Support\QueueWorkers\SupervisorQueueWorkerConfigBuilder;
use App\Support\RecipeRunner;
use App\Support\StepAftermathRegistry;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function queueWorkerSite(?User $user = null, array $attributes = [], bool $withRelease = true): array
{
    $user ??= User::factory()->create();
    $server = Server::factory()->for($user)->provisioned()->create([
        'ssh_user' => 'forge',
    ]);
    $site = Site::factory()->for($user)->for($server)->create([
        'domain' => 'stacklab.app',
        'root_path' => '/home/forge/stacklab.app',
        'php_version' => '8.4',
        'status' => SiteStatus::DEPLOYED,
        ...$attributes,
    ]);

    if ($withRelease) {
        $release = Release::factory()->for($site)->for($user)->active()->create();
        $site->forceFill(['current_release_id' => $release->id])->save();
    }

    return [$user, $server, $site];
}

function validWorkerPayload(array $overrides = []): array
{
    return [
        'name' => 'emails-worker',
        'connection' => 'redis',
        'queue' => 'emails',
        'php_version' => '8.4',
        'processes' => 3,
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'stopwaitsecs' => 3600,
        'restart_on_deploy' => true,
        ...$overrides,
    ];
}

test('guests cannot view the queues tab', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.queues', $site))
        ->assertRedirect(route('login'));
});

test('a user cannot view another users queues tab', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.queues', $site))
        ->assertForbidden();
});

test('the owner can view the queues tab', function () {
    [$user, $server, $site] = queueWorkerSite();

    $this->actingAs($user)
        ->get(route('sites.queues', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Show')
            ->where('tab', 'queues')
            ->where('site.uuid', $site->uuid)
            ->where('site.can_manage_queues', true)
            ->where('workers', [])
            ->where('php_versions', ['8.4'])
            ->where('operation', null)
        );
});

test('queue routes are bound by site uuid', function () {
    [$user, $server, $site] = queueWorkerSite();

    $this->actingAs($user)
        ->get('/sites/'.$site->id.'/queues')
        ->assertNotFound();
});

test('php sites cannot open the queues tab', function () {
    [$user, $server, $site] = queueWorkerSite(attributes: [
        'type' => 'PHP',
    ]);

    $this->actingAs($user)
        ->get(route('sites.queues', $site))
        ->assertNotFound();
});

test('html sites cannot open the queues tab', function () {
    [$user, $server, $site] = queueWorkerSite(attributes: [
        'type' => 'HTML',
    ]);

    $this->actingAs($user)
        ->get(route('sites.queues', $site))
        ->assertNotFound();
});

test('html sites cannot create queue workers', function () {
    [$user, $server, $site] = queueWorkerSite(attributes: [
        'type' => 'HTML',
    ]);

    $this->actingAs($user)
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload())
        ->assertNotFound();
});

test('an undeployed site cannot create a queue worker', function () {
    [$user, $server, $site] = queueWorkerSite(attributes: [
        'status' => SiteStatus::PENDING,
    ], withRelease: false);

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload())
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors(['site']);
});

test('the owner can start a queue worker install operation', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();

    $this->actingAs($user)
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload())
        ->assertRedirect(route('sites.queues', $site));

    $worker = QueueWorker::query()->first();
    $operation = $server->operations()->where('type', 'install_queue_worker')->first();

    expect($worker)->not->toBeNull()
        ->and($worker->name)->toBe('emails-worker')
        ->and($worker->connection)->toBe('redis')
        ->and($worker->queue)->toBe('emails')
        ->and($worker->php_version)->toBe('8.4')
        ->and($worker->processes)->toBe(3)
        ->and($worker->status)->toBe(QueueWorkerStatus::Installing)
        ->and($operation)->not->toBeNull()
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'supervisor.install@v1',
            'queue_worker.install@v1',
        ]);

    $args = $operation->steps->last()->arguments;
    $config = base64_decode((string) $args['supervisor_config_b64']);
    $command = (new QueueWorkerCommandBuilder)->build([
        'connection' => 'redis',
        'queue' => 'emails',
        'php_version' => '8.4',
        'sleep' => 3,
        'timeout' => 90,
        'tries' => 3,
        'backoff' => 0,
        'max_jobs' => 0,
        'max_time' => 0,
        'artisan_path' => '/home/forge/stacklab.app/current/artisan',
    ]);

    expect($args['supervisor_program'])->toBe((new SupervisorQueueWorkerConfigBuilder)->programName($site->id, $worker->id))
        ->and($args['supervisor_config_path'])->toBe('/etc/supervisor/conf.d/'.$args['supervisor_program'].'.conf')
        ->and($config)
        ->toContain('[program:'.$args['supervisor_program'].']')
        ->toContain('numprocs=3')
        ->toContain('user=forge')
        ->toContain('command='.$command)
        ->and($args)->not->toHaveKey('command');

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('worker names must be unique on a site', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    QueueWorker::factory()->for($site)->create(['name' => 'emails-worker']);

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload())
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors(['name']);
});

test('unsafe worker fields are rejected', function (array $payload) {
    [$user, $server, $site] = queueWorkerSite();

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload($payload))
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors();
})->with([
    'shell name' => [['name' => 'emails; rm']],
    'newline queue' => [['queue' => "default\njobs"]],
    'too many processes' => [['processes' => 99]],
    'stop wait below timeout' => [['timeout' => 90, 'stopwaitsecs' => 30]],
    'unknown php' => [['php_version' => '7.3']],
]);

test('a user cannot create a queue worker on another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->deployed()->create();

    $this->actingAs($user)
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload())
        ->assertForbidden();
});

test('a site cannot create a worker while another server operation is running', function () {
    [$user, $server, $site] = queueWorkerSite();
    $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => [],
    ]);

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->post(route('sites.queue-workers.store', $site), validWorkerPayload())
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors(['site']);
});

test('a successful install operation marks the worker installed', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installing()->create([
        'php_version' => '8.4',
        'processes' => 2,
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'install_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install queue worker',
        'recipe' => 'queue_worker.install@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'queue_worker.install',
            'success' => true,
            'changed' => true,
            'data' => ['running' => 2, 'configured' => 2],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    $worker->refresh();

    expect($worker->status)->toBe(QueueWorkerStatus::Installed)
        ->and($worker->installed_at)->not->toBeNull()
        ->and($operation->fresh()->status)->toBe('succeeded');
});

test('a failed install operation stores a diagnostic on the worker', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installing()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'install_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Install queue worker',
        'recipe' => 'queue_worker.install@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'queue_worker.install',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'worker_verify_failed', 'message' => 'Supervisor did not start 3 worker process(es).', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Failed)
        ->and($worker->fresh()->failure_message)->toBe('Supervisor did not start 3 worker process(es).')
        ->and($operation->fresh()->status)->toBe('failed');
});

test('the owner can query live supervisor status for installed workers', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'processes' => 2,
    ]);
    $program = $worker->supervisorProgram();

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, implode("\n", [
            "STACKLAB_PROGRAM_BEGIN:{$program}",
            "{$program}_00   RUNNING   pid 11, uptime 0:01:00",
            "{$program}_01   RUNNING   pid 12, uptime 0:01:00",
            "STACKLAB_PROGRAM_END:{$program}",
        ])));

    $this->actingAs($user)
        ->getJson(route('sites.queue-workers.status', $site))
        ->assertOk()
        ->assertJsonPath("workers.{$worker->uuid}.configured_processes", 2)
        ->assertJsonPath("workers.{$worker->uuid}.running_processes", 2)
        ->assertJsonPath("workers.{$worker->uuid}.healthy", true);
});

test('a user cannot query queue worker status for another users site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->getJson(route('sites.queue-workers.status', $site))
        ->assertForbidden();
});

test('updating a worker regenerates the supervisor config for the exact program', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'name' => 'emails-worker',
        'php_version' => '8.4',
        'processes' => 1,
        'queue' => 'emails',
    ]);

    $this->actingAs($user)
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload([
            'processes' => 3,
        ]))
        ->assertRedirect(route('sites.queues', $site));

    $worker->refresh();
    $operation = $server->operations()->where('type', 'update_queue_worker')->first();

    expect($worker->status)->toBe(QueueWorkerStatus::Updating)
        ->and($worker->processes)->toBe(3)
        ->and($operation)->not->toBeNull()
        ->and($operation->steps->pluck('recipe')->all())->toBe([
            'queue_worker.update@v1',
        ]);

    $args = $operation->steps->first()->arguments;
    $config = base64_decode((string) $args['supervisor_config_b64']);

    expect($args['supervisor_program'])->toBe($worker->supervisorProgram())
        ->and($args['supervisor_config_path'])->toBe($worker->supervisorConfigPath())
        ->and($config)
        ->toContain('[program:'.$args['supervisor_program'].']')
        ->toContain('numprocs=3');

    Queue::assertPushed(ProcessOperation::class, fn (ProcessOperation $job): bool => $job->operationId === $operation->id);
});

test('a worker can keep its own name when updating', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'name' => 'emails-worker',
        'queue' => 'default',
        'php_version' => '8.4',
    ]);

    $this->actingAs($user)
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload([
            'queue' => 'emails',
        ]))
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionDoesntHaveErrors();
});

test('worker names must stay unique on a site when updating', function () {
    [$user, $server, $site] = queueWorkerSite();
    QueueWorker::factory()->for($site)->installed()->create(['name' => 'taken']);
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'name' => 'emails-worker',
        'php_version' => '8.4',
    ]);

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload([
            'name' => 'taken',
        ]))
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors(['name']);
});

test('renaming a worker or toggling restart on deploy skips ssh', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'name' => 'emails-worker',
        'connection' => 'redis',
        'queue' => 'emails',
        'php_version' => '8.4',
        'processes' => 3,
        'restart_on_deploy' => true,
    ]);

    $this->actingAs($user)
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload([
            'name' => 'renamed-worker',
            'restart_on_deploy' => false,
        ]))
        ->assertRedirect(route('sites.queues', $site));

    $worker->refresh();

    expect($worker->name)->toBe('renamed-worker')
        ->and($worker->restart_on_deploy)->toBeFalse()
        ->and($worker->status)->toBe(QueueWorkerStatus::Installed)
        ->and($server->operations()->where('type', 'update_queue_worker')->exists())->toBeFalse();

    Queue::assertNothingPushed();
});

test('a busy worker cannot be updated', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installing()->create([
        'name' => 'emails-worker',
        'php_version' => '8.4',
    ]);

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload())
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors(['queue_worker']);
});

test('a site cannot update a worker while another server operation is running', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'name' => 'emails-worker',
        'php_version' => '8.4',
    ]);
    $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'provision',
        'status' => 'running',
        'plan_snapshot' => [],
    ]);

    $this->actingAs($user)
        ->from(route('sites.queues', $site))
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload([
            'processes' => 2,
        ]))
        ->assertRedirect(route('sites.queues', $site))
        ->assertSessionHasErrors(['site']);
});

test('the owner can restart an installed worker', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'php_version' => '8.4',
        'processes' => 2,
    ]);

    $this->actingAs($user)
        ->post(route('sites.queue-workers.restart', [$site, $worker]))
        ->assertRedirect(route('sites.queues', $site));

    $worker->refresh();
    $operation = $server->operations()->where('type', 'restart_queue_worker')->first();

    expect($worker->status)->toBe(QueueWorkerStatus::Restarting)
        ->and($operation)->not->toBeNull()
        ->and($operation->steps->pluck('recipe')->all())->toBe(['queue_worker.restart@v1'])
        ->and($operation->steps->first()->arguments['supervisor_program'])->toBe($worker->supervisorProgram())
        ->and($operation->steps->first()->arguments['supervisor_config_path'])->toBe($worker->supervisorConfigPath());
});

test('the owner can graceful restart an installed worker', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create([
        'php_version' => '8.4',
    ]);

    $this->actingAs($user)
        ->post(route('sites.queue-workers.graceful-restart', [$site, $worker]))
        ->assertRedirect(route('sites.queues', $site));

    $operation = $server->operations()->where('type', 'graceful_restart_queue_worker')->first();

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Restarting)
        ->and($operation->steps->pluck('recipe')->all())->toBe(['queue_worker.graceful_restart@v1'])
        ->and($operation->steps->first()->arguments['artisan_path'])->toBe($worker->artisanPath())
        ->and($operation->steps->first()->arguments['php_binary'])->toBe('/usr/bin/php8.4');
});

test('deleting a worker targets only that supervisor program', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create(['php_version' => '8.4']);
    $other = QueueWorker::factory()->for($site)->installed()->create(['php_version' => '8.4']);

    $this->actingAs($user)
        ->delete(route('sites.queue-workers.destroy', [$site, $worker]))
        ->assertRedirect(route('sites.queues', $site));

    $operation = $server->operations()->where('type', 'delete_queue_worker')->first();
    $args = $operation->steps->first()->arguments;

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Deleting)
        ->and($other->fresh()->status)->toBe(QueueWorkerStatus::Installed)
        ->and($operation->steps->pluck('recipe')->all())->toBe(['queue_worker.delete@v1'])
        ->and($args['supervisor_program'])->toBe($worker->supervisorProgram())
        ->and($args['supervisor_program'])->not->toBe($other->supervisorProgram())
        ->and($args['supervisor_config_path'])->toBe($worker->supervisorConfigPath())
        ->and($args['supervisor_config_path'])->not->toBe($other->supervisorConfigPath());
});

test('a failed worker can be deleted', function () {
    Queue::fake();

    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->failed()->create(['php_version' => '8.4']);

    $this->actingAs($user)
        ->delete(route('sites.queue-workers.destroy', [$site, $worker]))
        ->assertRedirect(route('sites.queues', $site));

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Deleting);
});

test('a user cannot manage another users queue worker', function () {
    $user = User::factory()->create();
    $site = Site::factory()->deployed()->create();
    $worker = QueueWorker::factory()->for($site)->installed()->create();

    $this->actingAs($user)
        ->put(route('sites.queue-workers.update', [$site, $worker]), validWorkerPayload())
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('sites.queue-workers.restart', [$site, $worker]))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('sites.queue-workers.destroy', [$site, $worker]))
        ->assertForbidden();
});

test('a queue worker from another site cannot be managed through scoped bindings', function () {
    [$user, $server, $site] = queueWorkerSite();
    $other = QueueWorker::factory()->installed()->create();

    $this->actingAs($user)
        ->put(route('sites.queue-workers.update', [$site, $other]), validWorkerPayload())
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('sites.queue-workers.restart', [$site, $other]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('sites.queue-workers.destroy', [$site, $other]))
        ->assertNotFound();
});

test('a successful update operation marks the worker installed', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->updating()->create([
        'php_version' => '8.4',
        'processes' => 2,
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'update_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Update queue worker',
        'recipe' => 'queue_worker.update@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'queue_worker.update',
            'success' => true,
            'changed' => true,
            'data' => ['running' => 2, 'configured' => 2],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Installed)
        ->and($worker->fresh()->installed_at)->not->toBeNull()
        ->and($operation->fresh()->status)->toBe('succeeded');
});

test('a failed update operation restores the previous worker attributes', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->updating()->create([
        'name' => 'emails-worker',
        'connection' => 'database',
        'queue' => 'new',
        'php_version' => '8.4',
        'processes' => 5,
    ]);
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'update_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
            'previous' => [
                'name' => 'emails-worker',
                'connection' => 'redis',
                'queue' => 'emails',
                'php_version' => '8.4',
                'processes' => 2,
                'sleep' => 3,
                'timeout' => 90,
                'tries' => 3,
                'backoff' => 0,
                'max_jobs' => 0,
                'max_time' => 0,
                'stopwaitsecs' => 3600,
                'restart_on_deploy' => true,
                'status' => 'installed',
            ],
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Update queue worker',
        'recipe' => 'queue_worker.update@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'queue_worker.update',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'worker_verify_failed', 'message' => 'Supervisor rejected the update.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    $worker->refresh();

    expect($worker->status)->toBe(QueueWorkerStatus::Installed)
        ->and($worker->connection)->toBe('redis')
        ->and($worker->queue)->toBe('emails')
        ->and($worker->processes)->toBe(2)
        ->and($worker->failure_message)->toBe('Supervisor rejected the update.');
});

test('a failed restart leaves the worker installed with a diagnostic', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->restarting()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'restart_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Restart queue worker',
        'recipe' => 'queue_worker.restart@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'queue_worker.restart',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'supervisor_restart_failed', 'message' => 'Supervisor could not restart the worker.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Installed)
        ->and($worker->fresh()->failure_message)->toBe('Supervisor could not restart the worker.');
});

test('a successful delete operation removes the worker row', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->deleting()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'delete_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Remove queue worker',
        'recipe' => 'queue_worker.delete@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'step_key' => 'queue_worker.delete',
            'success' => true,
            'changed' => true,
            'data' => [],
            'error' => ['code' => null, 'message' => null, 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect(QueueWorker::query()->find($worker->id))->toBeNull()
        ->and($operation->fresh()->status)->toBe('succeeded');
});

test('a failed delete operation restores the previous worker status', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->deleting()->create();
    $operation = $server->operations()->create([
        'user_id' => $user->id,
        'type' => 'delete_queue_worker',
        'plan_snapshot' => [
            'site_id' => $site->id,
            'queue_worker_id' => $worker->id,
            'previous' => [
                ...$worker->snapshotAttributes(),
                'status' => 'installed',
            ],
        ],
    ]);
    $operation->steps()->create([
        'position' => 1,
        'name' => 'Remove queue worker',
        'recipe' => 'queue_worker.delete@v1',
        'aftermath' => 'finalize_queue_worker',
        'status' => 'pending',
        'arguments' => [
            'queue_worker_id' => $worker->id,
        ],
    ]);

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(1, json_encode([
            'step_key' => 'queue_worker.delete',
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => ['code' => 'worker_still_present', 'message' => 'Supervisor still reports the worker program after deletion.', 'details' => null],
        ])));

    (new ProcessOperation($operation->id))->handle(
        app(RecipeRunner::class),
        app(StepAftermathRegistry::class),
    );

    expect($worker->fresh()->status)->toBe(QueueWorkerStatus::Installed)
        ->and($worker->fresh()->failure_message)->toBe('Supervisor still reports the worker program after deletion.');
});

test('the owner can read bounded worker logs from the managed path', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create();
    $log = "password=hunter2\nprocessed job\n";

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->withArgs(function ($sshServer, $fingerprint, string $script) use ($worker): bool {
            expect($script)
                ->toContain($worker->stdoutLogPath())
                ->toContain('tail -n')
                ->not->toContain('/etc/passwd')
                ->not->toContain('/tmp/evil.log');

            return true;
        })
        ->andReturn(new SshResult(0, implode("\n", [
            'LOG_BYTES:'.strlen($log),
            'LOG_OUTPUT_B64_BEGIN',
            base64_encode($log),
            'LOG_OUTPUT_B64_END',
        ])));

    $this->actingAs($user)
        ->getJson(route('sites.queue-workers.logs', [$site, $worker]).'?path=/etc/passwd')
        ->assertOk()
        ->assertJsonPath('truncated', false)
        ->assertJsonPath('output', "password=[REDACTED]\nprocessed job\n");
});

test('missing worker logs return an empty transcript', function () {
    [$user, $server, $site] = queueWorkerSite();
    $worker = QueueWorker::factory()->for($site)->installed()->create();

    $this->mock(SshService::class)
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, "LOG_MISSING\n"));

    $this->actingAs($user)
        ->getJson(route('sites.queue-workers.logs', [$site, $worker]))
        ->assertOk()
        ->assertJson([
            'output' => '',
            'truncated' => false,
        ]);
});

test('a user cannot read another users queue worker logs', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();
    $worker = QueueWorker::factory()->for($site)->installed()->create();

    $this->actingAs($user)
        ->getJson(route('sites.queue-workers.logs', [$site, $worker]))
        ->assertForbidden();
});
