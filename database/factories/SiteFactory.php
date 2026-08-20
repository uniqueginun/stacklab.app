<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
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
            'server_id' => Server::factory(),
            'domain' => fake()->unique()->domainName(),
            'type' => 'Laravel',
            'web_directory' => '/public',
            'status' => SiteStatus::PENDING,
        ];
    }

    public function withRepository(string $repository = 'octocat/hello', string $branch = 'main'): static
    {
        return $this->state(fn (): array => [
            'repository_url' => $repository,
            'repository_id' => 1,
            'repository_branch' => $branch,
        ]);
    }

    public function php(): static
    {
        return $this->state(fn (): array => [
            'type' => 'PHP',
            'web_directory' => '/',
        ]);
    }

    public function html(): static
    {
        return $this->state(fn (): array => [
            'type' => 'HTML',
            'web_directory' => '/',
        ]);
    }

    public function deployed(): static
    {
        return $this->state(fn (): array => [
            'status' => SiteStatus::DEPLOYED,
        ]);
    }
}
