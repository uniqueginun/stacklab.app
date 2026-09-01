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

class CreateCertificateSigningRequest
{
    use CreatesSslOperations;

    public function __construct(private SiteNginxConfig $nginxConfig) {}

    /**
     * @param  array{
     *     country: string,
     *     state: string,
     *     locality: string,
     *     organization: string,
     *     organizational_unit?: string|null,
     *     email?: string|null
     * }  $payload
     */
    public function handle(User $user, Site $site, array $payload): Operation
    {
        $this->assertSiteReadyForSsl($site);
        $this->assertNoBlockingCertificate($site);

        $certificate = $site->certificates()->create([
            'type' => SiteCertificateType::CSR,
            'status' => SiteCertificateStatus::PENDING,
            'domains' => SiteCertificate::domainsFor($site),
            'failure_message' => null,
        ]);

        $args = [
            ...$this->sslRecipeArguments($site, $certificate),
            'csr_country' => $payload['country'],
            'csr_state' => $payload['state'],
            'csr_locality' => $payload['locality'],
            'csr_organization' => $payload['organization'],
            'csr_organizational_unit' => $payload['organizational_unit'] ?? '',
            'csr_email' => $payload['email'] ?? $user->email,
            'csr_common_name' => $site->domain,
        ];

        return $this->dispatchSslOperation($user, $site, $certificate, [
            [
                'name' => 'Generate certificate signing request',
                'recipe' => 'ssl.csr.generate@v1',
                'aftermath' => FinalizeSslAftermath::key(),
                'arguments' => $args,
            ],
        ]);
    }
}
