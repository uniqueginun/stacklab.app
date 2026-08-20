<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'site_id' => Site::factory(),
            'user_id' => User::factory(),
            'commit_sha' => fake()->sha1(),
            'commit_message' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    public function rolledBack(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rolled_back',
            'activated_at' => now()->subHour(),
        ]);
    }

    public function deploying(): static
    {
        return $this->state(fn (): array => [
            'status' => 'deploying',
            'activated_at' => null,
        ]);
    }
}
