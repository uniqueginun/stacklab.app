<?php

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Models\User;
use App\Ssh\SshService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

test('management keys are generated in process without sudo', function () {
    Process::fake();

    $server = Server::factory()->create();
    $keys = app(SshService::class)->generateKeyPair($server);

    expect($keys['ssh_public_key'])->toStartWith('ssh-ed25519 ')
        ->and($keys['ssh_public_key'])->toContain('stacklab-management-'.$server->uuid)
        ->and($keys['ssh_private_key'])->toContain('BEGIN OPENSSH PRIVATE KEY');

    Process::assertDidntRun(function (PendingProcess $process): bool {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;

        return str_contains($command, 'sudo');
    });
});

test('a user can generate a management key without invoking sudo', function () {
    Process::fake();

    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('servers.ssh.connect', $server))
        ->assertRedirect(route('servers.show', $server));

    $server->refresh();

    expect($server->ssh_public_key)->toStartWith('ssh-ed25519 ')
        ->and($server->ssh_private_key)->toContain('BEGIN OPENSSH PRIVATE KEY')
        ->and($server->connection_status)->toBe(ConnectionStatus::UNVERIFIED);

    Process::assertDidntRun(function (PendingProcess $process): bool {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;

        return str_contains($command, 'sudo');
    });
});
