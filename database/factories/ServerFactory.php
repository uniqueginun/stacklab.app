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
}
