<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property array<string, mixed>|null $server_info
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

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /** @return HasMany<ServerDatabase, $this> */
    public function databases(): HasMany
    {
        return $this->hasMany(ServerDatabase::class);
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

    public function isProvisioned(): bool
    {
        return filled($this->profile);
    }

    public function hasMysql(): bool
    {
        return $this->isProvisioned() && $this->profile === 'php';
    }

    public function provisionedPhpVersion(): ?string
    {
        $operation = $this->operations()
            ->where('type', 'provision')
            ->where('status', 'succeeded')
            ->latest('id')
            ->first();

        $version = data_get($operation?->plan_snapshot, 'php_version');

        return is_string($version) && preg_match('/^\d+\.\d+$/', $version) === 1
            ? $version
            : null;
    }

    public function canProvision(): bool
    {
        return $this->isConnected() && ! $this->isProvisioned();
    }

    public function osId(): ?string
    {
        $os = data_get($this->server_info, 'os');

        return is_string($os) && $os !== '' ? strtolower($os) : null;
    }

    public function osVersion(): ?string
    {
        $version = data_get($this->server_info, 'os_version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function osLabel(): ?string
    {
        $pretty = data_get($this->server_info, 'os_pretty');

        if (is_string($pretty) && $pretty !== '') {
            return $pretty;
        }

        $os = $this->osId();
        $version = $this->osVersion();

        if ($os === null) {
            return null;
        }

        return trim(ucfirst($os).($version !== null ? ' '.$version : ''));
    }
}
