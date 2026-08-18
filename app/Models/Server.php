<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property string $name
 * @property string $provider
 * @property string $host
 * @property string $ssh_port
 * @property string $ssh_user
 * @property ConnectionStatus $connection_status
 */
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['ssh_private_key'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection_status' => ConnectionStatus::class,
            'ssh_private_key' => 'encrypted',
            'server_info' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Server $server): void {
            $server->uuid ??= (string) Str::uuid();
            $server->connection_status ??= ConnectionStatus::UNVERIFIED;
            $server->user_id ??= auth()->id();
        });
    }

    public function canStartSshSetup(): bool
    {
        return in_array($this->connection_status, [
            ConnectionStatus::UNVERIFIED,
            ConnectionStatus::FAILED,
        ], true);
    }

    public function hasSshKeyPair(): bool
    {
        return filled($this->ssh_public_key) && filled($this->ssh_private_key);
    }

    public function isConnected(): bool
    {
        return $this->connection_status === ConnectionStatus::CONNECTED
            && $this->verified_at !== null
            && filled($this->host_key)
            && filled($this->host_key_fingerprint)
            && $this->hasSshKeyPair();
    }
}
