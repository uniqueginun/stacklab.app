<?php

namespace App\Actions\Operations;

use App\Models\Operation;
use App\Models\Server;
use App\Models\User;
use App\Support\ProvisioningProfiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProvisionOperation
{
    public function __construct(protected readonly ProvisioningProfiles $profiles) {}

    /**
     * @param  array{profile: string, php_version?: string|null, mysql_version?: string|null}  $attributes
     */
    public function handle(array $attributes, Server $server, User $user): Operation
    {
        return DB::transaction(function () use ($server, $user, $attributes): Operation {
            $lockedServer = Server::query()->lockForUpdate()->findOrFail($server->id);

            if ($lockedServer->isProvisioned()) {
                throw ValidationException::withMessages([
                    'profile' => 'This server has already been provisioned.',
                ]);
            }

            if ($lockedServer->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'server' => 'This server already has an active operation.',
                ]);
            }

            $profile = $attributes['profile'];
            $phpVersion = $attributes['php_version'] ?? null;
            $mysqlVersion = $attributes['mysql_version'] ?? null;
            $steps = $this->profiles->steps($profile, $phpVersion, $mysqlVersion);

            $operation = $lockedServer->operations()->create([
                'user_id' => $user->id,
                'type' => 'provision',
                'plan_snapshot' => [
                    'profile' => $profile,
                    'php_version' => $phpVersion,
                    'mysql_version' => $mysqlVersion,
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            return $operation;
        }, 5);
    }
}
