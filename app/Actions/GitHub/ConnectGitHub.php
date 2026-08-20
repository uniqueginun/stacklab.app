<?php

namespace App\Actions\GitHub;

use App\Models\GitHubConnection;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

class ConnectGitHub
{
    public function handle(User $user, SocialiteUser $githubUser): GitHubConnection
    {
        $token = $githubUser->token;

        if ($token === '') {
            throw new \RuntimeException('GitHub did not return an access token.');
        }

        return GitHubConnection::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'github_id' => (string) $githubUser->getId(),
                'username' => (string) ($githubUser->getNickname() ?: $githubUser->getName()),
                'token' => $token,
            ],
        );
    }
}
