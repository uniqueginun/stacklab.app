<?php

namespace App\Actions\Operations;

use App\Jobs\ProcessOperation;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\User;
use App\Operations\Aftermath\FinalizeDatabaseAftermath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateServerDatabase
{
    /**
     * @param  array{name: string}  $attributes
     */
    public function handle(User $user, Server $server, array $attributes): ServerDatabase
    {
        $name = $attributes['name'];
        $username = $this->usernameFor($name);
        $password = Str::password(32, letters: true, numbers: true, symbols: true, spaces: false);

        [$database, $operation] = DB::transaction(function () use ($user, $server, $name, $username, $password): array {
            $lockedServer = Server::query()->lockForUpdate()->findOrFail($server->id);

            if (! $lockedServer->hasMysql()) {
                throw ValidationException::withMessages([
                    'name' => 'This server does not have MySQL installed.',
                ]);
            }

            if ($lockedServer->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'This server already has an active operation.',
                ]);
            }

            $database = $lockedServer->databases()->create([
                'user_id' => $user->id,
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'status' => 'pending',
            ]);

            $steps = [
                [
                    'name' => 'Create database',
                    'recipe' => 'database.create@v1',
                    'aftermath' => FinalizeDatabaseAftermath::key(),
                    'arguments' => [
                        'db_name' => $database->name,
                        'db_username' => $database->username,
                        'database_id' => $database->id,
                    ],
                ],
            ];

            $operation = $lockedServer->operations()->create([
                'user_id' => $user->id,
                'type' => 'create_database',
                'status' => 'pending',
                'plan_snapshot' => [
                    'database_id' => $database->id,
                    'database_uuid' => $database->uuid,
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            return [$database, $operation];
        }, attempts: 3);

        ProcessOperation::dispatch($operation->id);

        return $database;
    }

    private function usernameFor(string $name): string
    {
        $username = Str::lower($name);

        if (Str::length($username) > 32) {
            $username = Str::substr($username, 0, 32);
        }

        return $username;
    }
}
