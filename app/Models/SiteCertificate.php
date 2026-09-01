<?php

namespace App\Models;

use App\Enums\SiteCertificateStatus;
use App\Enums\SiteCertificateType;
use Database\Factories\SiteCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SiteCertificate extends Model
{
    /** @use HasFactory<SiteCertificateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'certificate',
        'private_key',
        'chain',
    ];

    protected static function booted(): void
    {
        static::creating(function (SiteCertificate $certificate): void {
            $certificate->uuid ??= (string) Str::uuid();
            $certificate->status ??= SiteCertificateStatus::PENDING;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SiteCertificateType::class,
            'status' => SiteCertificateStatus::class,
            'domains' => 'array',
            'certificate' => 'encrypted',
            'private_key' => 'encrypted',
            'chain' => 'encrypted',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isLetsEncrypt(): bool
    {
        return $this->type === SiteCertificateType::LETS_ENCRYPT;
    }

    public function isActive(): bool
    {
        return $this->status === SiteCertificateStatus::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === SiteCertificateStatus::PENDING;
    }

    public function isAwaitingCertificate(): bool
    {
        return $this->status === SiteCertificateStatus::AWAITING_CERTIFICATE;
    }

    /**
     * @return list<string>
     */
    public static function domainsFor(Site $site, bool $includeWww = false): array
    {
        $domain = $site->domain;

        if ($includeWww && ! str_starts_with(strtolower($domain), 'www.')) {
            return [$domain, 'www.'.$domain];
        }

        return [$domain];
    }

    public function primaryDomain(): string
    {
        $domains = $this->domains;

        if (is_array($domains) && isset($domains[0]) && is_string($domains[0]) && $domains[0] !== '') {
            return $domains[0];
        }

        return $this->site->domain;
    }

    public function certificatePath(): string
    {
        if ($this->isLetsEncrypt()) {
            return '/etc/letsencrypt/live/'.$this->primaryDomain().'/fullchain.pem';
        }

        return '/etc/nginx/ssl/'.$this->primaryDomain().'/fullchain.pem';
    }

    public function privateKeyPath(): string
    {
        if ($this->isLetsEncrypt()) {
            return '/etc/letsencrypt/live/'.$this->primaryDomain().'/privkey.pem';
        }

        return '/etc/nginx/ssl/'.$this->primaryDomain().'/privkey.pem';
    }

    public function wipePrivateMaterials(): void
    {
        $this->forceFill([
            'certificate' => null,
            'private_key' => null,
            'chain' => null,
        ])->save();
    }
}
