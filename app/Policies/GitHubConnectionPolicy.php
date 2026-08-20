<?php

namespace App\Policies;

use App\Models\GitHubConnection;
use App\Models\User;

class GitHubConnectionPolicy
{
    /**
     * Determine whether the user can connect a GitHub account.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can disconnect the GitHub account.
     */
    public function delete(User $user, GitHubConnection $gitHubConnection): bool
    {
        return $gitHubConnection->user_id === $user->id;
    }
}
