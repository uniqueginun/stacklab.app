<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerDatabase>
 */
class ServerDatabaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'db_'.fake()->unique()->numerify('####');

        return [
            'user_id' => User::factory(),
            'server_id' => Server::factory(),
            'name' => $name,
            'username' => $name,
            'password' => 'secret-password',
            'status' => 'pending',
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => 'ready',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'failure_message' => 'Unable to create the database.',
        ]);
    }
}
