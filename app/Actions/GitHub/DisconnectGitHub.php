<?php

namespace App\Actions\GitHub;

use App\Models\GitHubConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class DisconnectGitHub
{
    public function handle(User $user): void
    {
        $connection = $user->githubConnection;

        if ($connection === null) {
            return;
        }

        $this->revokeToken($connection);

        $connection->delete();
    }

    private function revokeToken(GitHubConnection $connection): void
    {
        $clientId = config('services.github.client_id');
        $clientSecret = config('services.github.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return;
        }

        try {
            $url = 'https://api.github.com/applications/'.$clientId.'/token';

            Http::withBasicAuth($clientId, $clientSecret)->acceptJson()->delete($url, [
                'access_token' => $connection->token,
            ]);
        } catch (\Throwable) {
        }
    }
}
