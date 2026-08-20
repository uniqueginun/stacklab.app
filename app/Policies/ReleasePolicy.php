<?php

namespace App\Policies;

use App\Models\Release;
use App\Models\User;

class ReleasePolicy
{
    /**
     * Determine whether the user can roll the site back to this release.
     */
    public function rollback(User $user, Release $release): bool
    {
        $release->loadMissing('site');

        $site = $release->site;

        return $site !== null && $user->can('update', $site);
    }
}
