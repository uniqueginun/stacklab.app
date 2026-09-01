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
use Illuminate\Validation\ValidationException;

class InstallSignedCertificate
{
    use CreatesSslOperations;

    public function __construct(private SiteNginxConfig $nginxConfig) {}

    /**
     * @param  array{certificate: string, chain?: string|null}  $payload
     */
    public function handle(User $user, Site $site, SiteCertificate $certificate, array $payload): Operation
    {
        $this->assertSiteReadyForSsl($site);

        if ($certificate->site_id !== $site->id) {
            throw ValidationException::withMessages([
                'certificate' => 'The selected certificate is invalid.',
            ]);
        }

        if ($certificate->type !== SiteCertificateType::CSR || ! $certificate->isAwaitingCertificate()) {
            throw ValidationException::withMessages([
                'certificate' => 'Install a signed certificate against a generated CSR.',
            ]);
        }

        $certificate->forceFill([
            'status' => SiteCertificateStatus::PENDING,
            'certificate' => $payload['certificate'],
            'chain' => $payload['chain'] ?? null,
            'failure_message' => null,
        ])->save();

        $args = $this->sslRecipeArguments($site, $certificate);

        return $this->dispatchSslOperation($user, $site, $certificate, [
            [
                'name' => 'Install signed certificate',
                'recipe' => 'ssl.csr.install@v1',
                'aftermath' => FinalizeSslAftermath::key(),
                'arguments' => $args,
            ],
        ]);
    }
}
