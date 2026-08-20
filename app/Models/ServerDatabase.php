<?php

namespace App\Models;

use Database\Factories\ServerDatabaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServerDatabase extends Model
{
    /** @use HasFactory<ServerDatabaseFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::creating(function (ServerDatabase $database): void {
            $database->uuid ??= (string) Str::uuid();
            $database->status ??= 'pending';
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
