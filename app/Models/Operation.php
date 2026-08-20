<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Operation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Operation $operation): void {
            $operation->uuid ??= (string) Str::uuid();
            $operation->status ??= 'pending';
        });
    }

    protected function casts(): array
    {
        return [
            'plan_snapshot' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OperationStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(OperationStep::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function start(): void
    {
        $this->forceFill([
            'status' => 'running',
            'started_at' => $this->started_at ?? now(),
            'failure_message' => null,
        ])->save();
    }

    public function succeed(): void
    {
        $this->forceFill([
            'status' => 'succeeded',
            'finished_at' => now(),
            'failure_message' => null,
        ])->save();
    }

    public function getProfile(): ?string
    {
        $profile = data_get($this->plan_snapshot, 'profile');

        return is_string($profile) ? $profile : null;
    }

    public function fail(string $message): void
    {
        $this->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'failure_message' => mb_substr($message, 0, 2000),
        ])->save();
    }
}
