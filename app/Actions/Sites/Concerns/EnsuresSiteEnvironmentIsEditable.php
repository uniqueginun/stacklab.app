<?php

namespace App\Actions\Sites\Concerns;

use App\Enums\ConnectionStatus;
use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;

trait EnsuresSiteEnvironmentIsEditable
{
    private function assertEditable(User $user, Site $site): void
    {
        if (! $user->is($site->user)) {
            throw ValidationException::withMessages([
                'site' => 'The selected site is invalid.',
            ]);
        }

        if (! $site->isPhp()) {
            throw ValidationException::withMessages([
                'site' => 'Environment files are only available for PHP sites.',
            ]);
        }

        if ($site->status !== SiteStatus::DEPLOYED) {
            throw ValidationException::withMessages([
                'site' => 'The site must be deployed before editing the environment file.',
            ]);
        }

        if (! $site->hasUsableRootPath()) {
            throw ValidationException::withMessages([
                'site' => 'The site path is not ready yet.',
            ]);
        }

        $site->loadMissing('server');
        $server = $site->server;

        if ($server->connection_status !== ConnectionStatus::CONNECTED || ! is_string($server->host_key) || $server->host_key === '') {
            throw ValidationException::withMessages([
                'site' => 'Verify the server SSH connection before editing the environment file.',
            ]);
        }
    }
}
