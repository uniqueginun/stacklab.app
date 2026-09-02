<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->domainWord().'-'.fake()->numerify('###'),
            'provider' => fake()->randomElement(['digitalocean', 'custom']),
            'host' => fake()->ipv4(),
            'ssh_port' => '22',
            'ssh_user' => 'root',
            'connection_status' => ConnectionStatus::UNVERIFIED,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $serverInfo
     */
    public function connected(?array $serverInfo = null): static
    {
        return $this->state(fn (): array => [
            'connection_status' => ConnectionStatus::CONNECTED,
            'ssh_public_key' => 'ssh-ed25519 public-key stacklab',
            'ssh_private_key' => 'private-key',
            'host_key' => 'ssh-ed25519 host-key',
            'host_key_fingerprint' => 'SHA256:host-fingerprint',
            'verified_at' => now(),
            'server_info' => $serverInfo ?? [
                'os' => 'ubuntu',
                'os_version' => '24.04',
                'os_pretty' => 'Ubuntu 24.04 LTS',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $serverInfo
     */
    public function oracleLinux(?array $serverInfo = null): static
    {
        return $this->connected($serverInfo ?? [
            'os' => 'ol',
            'os_version' => '9.8',
            'os_pretty' => 'Oracle Linux Server 9.8',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $serverInfo
     */
    public function provisioned(string $profile = 'php', ?array $serverInfo = null): static
    {
        return $this->connected($serverInfo)->state(fn (): array => [
            'profile' => $profile,
        ]);
    }
}
