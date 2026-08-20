<?php

namespace Database\Factories;

use App\Models\GitHubConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GitHubConnection>
 */
class GitHubConnectionFactory extends Factory
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
            'github_id' => (string) fake()->unique()->numerify('########'),
            'username' => fake()->unique()->userName(),
            'token' => 'github-token-'.fake()->sha256(),
        ];
    }
}
