<?php

namespace App\Actions\Operations;

use App\Actions\Operations\Concerns\CreatesSslOperations;
use App\Enums\SiteCertificateStatus;
use App\Enums\SiteCertificateType;
use App\Models\Operation;
use App\Models\Site;
use App\Models\SiteCertificate;
use App\Models\User;
use App\Operations\Aftermath\FinalizeSslAftermath;
use App\Support\SiteNginxConfig;

class ObtainLetsEncryptCertificate
{
    use CreatesSslOperations;

    public function __construct(private SiteNginxConfig $nginxConfig) {}

    public function handle(User $user, Site $site, bool $includeWww = false): Operation
    {
        $this->assertSiteReadyForSsl($site);
        $this->assertNoBlockingCertificate($site);

        $certificate = $site->certificates()->create([
            'type' => SiteCertificateType::LETS_ENCRYPT,
            'status' => SiteCertificateStatus::PENDING,
            'domains' => SiteCertificate::domainsFor($site, $includeWww),
            'failure_message' => null,
        ]);

        $args = [
            ...$this->sslRecipeArguments($site, $certificate),
            'letsencrypt_email' => $user->email,
        ];

        return $this->dispatchSslOperation($user, $site, $certificate, [
            [
                'name' => 'Install Certbot',
                'recipe' => 'ssl.certbot.install@v1',
                'arguments' => $args,
            ],
            [
                'name' => "Obtain Let's Encrypt certificate",
                'recipe' => 'ssl.letsencrypt.obtain@v1',
                'aftermath' => FinalizeSslAftermath::key(),
                'arguments' => $args,
            ],
        ]);
    }
}
