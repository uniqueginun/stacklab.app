<?php

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Models\User;
use App\Ssh\HostFingerprint;
use App\Ssh\SshResult;
use App\Ssh\SshService;
use Inertia\Testing\AssertableInertia as Assert;

test('the server page exposes the ssh setup state without exposing the private key', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'ssh_public_key' => 'ssh-ed25519 public-key stacklab',
        'ssh_private_key' => 'private-key',
    ]);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('server.ssh_public_key', 'ssh-ed25519 public-key stacklab')
            ->where('server.is_connected', false)
            ->where('sshFingerprint', null)
            ->where('sshHostKeyType', null)
            ->missing('server.ssh_private_key')
        );
});

test('a user can generate a management key for an unverified server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->mock(SshService::class)
        ->shouldReceive('generateKeyPair')
        ->once()
        ->withArgs(fn (Server $argument) => $argument->is($server))
        ->andReturn([
            'known_hosts_path' => '/tmp/known-hosts',
            'host_key_fingerprint' => 'SHA256:management-key',
            'ssh_public_key' => 'ssh-ed25519 public-key stacklab',
            'ssh_private_key' => 'private-key',
        ]);

    $this->actingAs($user)
        ->post(route('servers.ssh.connect', $server))
        ->assertRedirect(route('servers.show', $server));

    $server->refresh();

    expect($server->ssh_public_key)->toBe('ssh-ed25519 public-key stacklab')
        ->and($server->ssh_private_key)->toBe('private-key')
        ->and($server->getRawOriginal('ssh_private_key'))->not->toBe('private-key')
        ->and($server->host_key_fingerprint)->toBeNull()
        ->and($server->connection_status)->toBe(ConnectionStatus::UNVERIFIED);
});

test('a user can discover the host fingerprint after installing the public key', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'ssh_public_key' => 'ssh-ed25519 public-key stacklab',
        'ssh_private_key' => 'private-key',
    ]);
    $host = new HostFingerprint('ssh-ed25519 host-key', 'SHA256:host-fingerprint');

    $this->mock(SshService::class)
        ->shouldReceive('discoverHost')
        ->once()
        ->andReturn($host);

    $this->actingAs($user)
        ->post(route('servers.ssh.verify', $server))
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionHas('servers.connection.'.$server->uuid, [
            'key' => $host->key,
            'fingerprint' => $host->fingerprint,
        ]);

    expect($server->refresh()->connection_status)
        ->toBe(ConnectionStatus::PENDING_CONFIRMATION);
});

test('confirming a matching fingerprint stores the ssh host and unlocks the server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::PENDING_CONFIRMATION,
        'ssh_public_key' => 'ssh-ed25519 public-key stacklab',
        'ssh_private_key' => 'private-key',
    ]);
    $host = new HostFingerprint('ssh-ed25519 host-key', 'SHA256:host-fingerprint');

    $this->mock(SshService::class)
        ->shouldReceive('verifyConnection')
        ->once()
        ->withArgs(fn (Server $argument, HostFingerprint $fingerprint) => $argument->is($server)
            && $fingerprint->key === $host->key
            && $fingerprint->fingerprint === $host->fingerprint)
        ->andReturn(['os' => 'ubuntu', 'os_version' => '24.04'])
        ->shouldReceive('run')
        ->once()
        ->andReturn(new SshResult(0, json_encode([
            'data' => [
                'os' => 'ubuntu',
                'os_version' => '24.04',
            ],
        ])));

    $this->actingAs($user)
        ->withSession([
            'servers.connection.'.$server->uuid => [
                'key' => $host->key,
                'fingerprint' => $host->fingerprint,
            ],
        ])
        ->post(route('servers.ssh.confirm', $server))
        ->assertRedirect(route('servers.show', $server))
        ->assertSessionMissing('servers.connection.'.$server->uuid);

    $server->refresh();

    expect($server->connection_status)->toBe(ConnectionStatus::CONNECTED)
        ->and($server->host_key)->toBe($host->key)
        ->and($server->host_key_fingerprint)->toBe($host->fingerprint)
        ->and($server->verified_at)->not->toBeNull()
        ->and($server->server_info)->toMatchArray([
            'os' => 'ubuntu',
            'os_version' => '24.04',
        ])
        ->and($server->isConnected())->toBeTrue();
});

test('another user cannot run ssh setup actions on a server', function () {
    $server = Server::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('servers.ssh.connect', $server))
        ->assertNotFound();
});

test('a connected server cannot generate a replacement key through setup', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'connection_status' => ConnectionStatus::CONNECTED,
        'ssh_public_key' => 'ssh-ed25519 public-key stacklab',
        'ssh_private_key' => 'private-key',
        'host_key' => 'ssh-ed25519 host-key',
        'host_key_fingerprint' => 'SHA256:host-fingerprint',
        'verified_at' => now(),
    ]);

    $this->mock(SshService::class)->shouldNotReceive('generateKeyPair');

    $this->actingAs($user)
        ->post(route('servers.ssh.connect', $server))
        ->assertBadRequest();
});
