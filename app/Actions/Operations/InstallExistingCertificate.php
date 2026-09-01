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

class InstallExistingCertificate
{
    use CreatesSslOperations;

    public function __construct(private SiteNginxConfig $nginxConfig) {}

    /**
     * @param  array{certificate: string, private_key: string, chain?: string|null}  $payload
     */
    public function handle(User $user, Site $site, array $payload): Operation
    {
        $this->assertSiteReadyForSsl($site);
        $this->assertNoBlockingCertificate($site);

        $certificate = $site->certificates()->create([
            'type' => SiteCertificateType::EXISTING,
            'status' => SiteCertificateStatus::PENDING,
            'domains' => SiteCertificate::domainsFor($site),
            'certificate' => $payload['certificate'],
            'private_key' => $payload['private_key'],
            'chain' => $payload['chain'] ?? null,
            'failure_message' => null,
        ]);

        $args = $this->sslRecipeArguments($site, $certificate);

        return $this->dispatchSslOperation($user, $site, $certificate, [
            [
                'name' => 'Install existing certificate',
                'recipe' => 'ssl.existing.install@v1',
                'aftermath' => FinalizeSslAftermath::key(),
                'arguments' => $args,
            ],
        ]);
    }
}
