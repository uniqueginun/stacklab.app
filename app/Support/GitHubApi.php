<?php

namespace App\Support;

use App\Models\GitHubConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GitHubApi
{
    public function __construct(private GitHubConnection $connection) {}

    /**
     * @return list<array{id: int, full_name: string, private: bool, default_branch: string}>
     */
    public function repositories(): array
    {
        $repositories = [];
        $page = 1;

        do {
            $response = $this->client()->get('/user/repos', [
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'affiliation' => 'owner,collaborator,organization_member',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to list GitHub repositories.');
            }

            /** @var list<array<string, mixed>> $batch */
            $batch = $response->json() ?? [];

            foreach ($batch as $repo) {
                $repositories[] = [
                    'id' => (int) $repo['id'],
                    'full_name' => (string) $repo['full_name'],
                    'private' => (bool) ($repo['private'] ?? false),
                    'default_branch' => (string) ($repo['default_branch'] ?? 'main'),
                ];
            }

            $page++;
        } while (count($batch) === 100 && $page <= 5);

        return $repositories;
    }

    /**
     * @return list<array{name: string}>
     */
    public function branches(string $owner, string $repo): array
    {
        $response = $this->client()->get("/repos/{$owner}/{$repo}/branches", [
            'per_page' => 100,
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'repository' => 'Unable to list branches for the selected repository.',
            ]);
        }

        /** @var list<array<string, mixed>> $payload */
        $payload = $response->json() ?? [];

        return array_map(
            fn (array $branch): array => ['name' => (string) $branch['name']],
            $payload,
        );
    }

    /**
     * @return array{sha: string, message: string|null}
     */
    public function branchHead(string $owner, string $repo, string $branch): array
    {
        $response = $this->client()->get("/repos/{$owner}/{$repo}/commits/{$branch}");

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'branch' => 'Unable to resolve the selected branch tip.',
            ]);
        }

        return [
            'sha' => (string) $response->json('sha'),
            'message' => is_string($response->json('commit.message'))
                ? mb_substr((string) $response->json('commit.message'), 0, 255)
                : null,
        ];
    }

    /**
     * @return array{id: int, title: string, key: string, fingerprint: string}
     */
    public function createDeployKey(string $owner, string $repo, string $title, string $publicKey): array
    {
        $response = $this->client()->post("/repos/{$owner}/{$repo}/keys", [
            'title' => $title,
            'key' => $publicKey,
            'read_only' => true,
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'repository' => 'Unable to create a GitHub deploy key for this repository.',
            ]);
        }

        return [
            'id' => (int) $response->json('id'),
            'title' => (string) $response->json('title'),
            'key' => (string) $response->json('key'),
            'fingerprint' => (string) ($response->json('fingerprint') ?? ''),
        ];
    }

    public function deleteDeployKey(string $owner, string $repo, int $keyId): void
    {
        $this->client()->delete("/repos/{$owner}/{$repo}/keys/{$keyId}");
    }

    /**
     * @return array{id: int, full_name: string, private: bool, default_branch: string}
     */
    public function repository(string $owner, string $repo): array
    {
        $response = $this->client()->get("/repos/{$owner}/{$repo}");

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'repository' => 'The selected repository could not be found.',
            ]);
        }

        return [
            'id' => (int) $response->json('id'),
            'full_name' => (string) $response->json('full_name'),
            'private' => (bool) $response->json('private'),
            'default_branch' => (string) ($response->json('default_branch') ?? 'main'),
        ];
    }

    private function client(): PendingRequest
    {
        return Http::github($this->connection->token);
    }
}
