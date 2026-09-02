<?php

namespace App\Models;

use App\Enums\SiteCertificateStatus;
use App\Enums\SiteStatus;
use App\Support\SiteDeploymentOptions;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $user_id
 * @property int $server_id
 * @property string $uuid
 * @property string $domain
 * @property string $type
 * @property string|null $php_version
 * @property string|null $web_directory
 * @property string|null $root_path
 * @property string|null $repository_url
 * @property int|null $repository_id
 * @property string|null $repository_branch
 * @property array<string, mixed>|null $deployment_options
 * @property int|null $current_release_id
 * @property SiteStatus $status
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return HasMany<Release, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /** @return HasMany<SiteCertificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(SiteCertificate::class);
    }

    /** @return HasMany<QueueWorker, $this> */
    public function queueWorkers(): HasMany
    {
        return $this->hasMany(QueueWorker::class);
    }

    public function activeCertificate(): ?SiteCertificate
    {
        if ($this->relationLoaded('certificates')) {
            $active = $this->certificates
                ->first(fn (SiteCertificate $certificate): bool => $certificate->isActive());

            if ($active !== null) {
                return $active;
            }
        }

        return $this->certificates()
            ->where('status', SiteCertificateStatus::ACTIVE)
            ->latest('id')
            ->first();
    }

    public function inFlightCertificate(): ?SiteCertificate
    {
        return $this->certificates()
            ->whereIn('status', [
                SiteCertificateStatus::PENDING,
                SiteCertificateStatus::AWAITING_CERTIFICATE,
            ])
            ->latest('id')
            ->first();
    }

    public function displayCertificate(): ?SiteCertificate
    {
        return $this->inFlightCertificate()
            ?? $this->activeCertificate()
            ?? $this->certificates()->latest('id')->first();
    }

    public function latestSslOperation(): ?Operation
    {
        return Operation::query()
            ->where('type', 'ssl')
            ->where('server_id', $this->server_id)
            ->where('plan_snapshot->site_id', $this->id)
            ->with(['steps' => fn ($query) => $query->orderBy('position')])
            ->latest('id')
            ->first();
    }

    public function latestQueueWorkerOperation(): ?Operation
    {
        return Operation::query()
            ->whereIn('type', QueueWorker::operationTypes())
            ->where('server_id', $this->server_id)
            ->where('plan_snapshot->site_id', $this->id)
            ->with(['steps' => fn ($query) => $query->orderBy('position')])
            ->latest('id')
            ->first();
    }

    public function hasActiveSsl(): bool
    {
        return $this->activeCertificate() !== null;
    }

    /** @return BelongsTo<Release, $this> */
    public function currentRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'current_release_id');
    }

    public function isLaravel(): bool
    {
        return strcasecmp($this->type, 'laravel') === 0;
    }

    public function isPhp(): bool
    {
        return $this->isLaravel() || strcasecmp($this->type, 'php') === 0;
    }

    public function environmentFilePath(): string
    {
        return rtrim((string) $this->root_path, '/').'/shared/.env';
    }

    public function currentPath(): string
    {
        return rtrim((string) $this->root_path, '/').'/current';
    }

    public function phpBinary(): string
    {
        $version = $this->php_version;

        if (is_string($version) && preg_match('/^\d+\.\d+$/', $version) === 1) {
            return 'php'.$version;
        }

        return 'php';
    }

    /**
     * @return array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }
     */
    public function resolvedDeploymentOptions(): array
    {
        return SiteDeploymentOptions::normalize(
            is_array($this->deployment_options) ? $this->deployment_options : null,
        );
    }

    public function latestDeploymentOperation(): ?Operation
    {
        $operationIds = $this->releases()
            ->whereNotNull('operation_id')
            ->pluck('operation_id');

        if ($operationIds->isEmpty()) {
            return null;
        }

        return Operation::query()
            ->whereIn('id', $operationIds)
            ->with(['steps' => fn ($query) => $query->orderBy('position')])
            ->latest('id')
            ->first();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function rootPathFor(Server $server, string $domain): string
    {
        $user = filled($server->ssh_user) ? (string) $server->ssh_user : 'root';
        $user = str_replace(['/', '\\', "\0"], '', $user);
        $safeDomain = str_replace(['/', '\\', "\0"], '', $domain);

        if ($user === '' || $safeDomain === '' || str_contains($safeDomain, '..')) {
            throw new InvalidArgumentException('The site domain cannot be used as a filesystem path.');
        }

        return $user === 'root'
            ? '/var/www/'.$safeDomain
            : '/home/'.$user.'/'.$safeDomain;
    }

    public function hasUsableRootPath(): bool
    {
        return is_string($this->root_path)
            && str_starts_with($this->root_path, '/')
            && ! str_contains($this->root_path, '..')
            && ! str_contains($this->root_path, "\0");
    }

    public function ensureRootPath(): string
    {
        if ($this->hasUsableRootPath()) {
            return (string) $this->root_path;
        }

        $this->loadMissing('server');

        $path = self::rootPathFor($this->server, $this->domain);
        $this->forceFill(['root_path' => $path])->save();

        return $path;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'deployment_options' => 'array',
            'last_deployed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            $site->uuid ??= (string) Str::uuid();
            $site->status ??= SiteStatus::PENDING;
            $site->user_id ??= auth()->id();
        });
    }
}
