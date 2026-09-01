<?php

namespace App\Actions\Operations;

use App\Actions\Operations\Concerns\CreatesSslOperations;
use App\Models\Operation;
use App\Models\Site;
use App\Models\SiteCertificate;
use App\Models\User;
use App\Operations\Aftermath\FinalizeSslAftermath;
use App\Support\SiteNginxConfig;
use Illuminate\Validation\ValidationException;

class DeactivateSiteCertificate
{
    use CreatesSslOperations;

    public function __construct(private SiteNginxConfig $nginxConfig) {}

    public function handle(User $user, Site $site, SiteCertificate $certificate): ?Operation
    {
        if ($certificate->site_id !== $site->id) {
            throw ValidationException::withMessages([
                'certificate' => 'The selected certificate is invalid.',
            ]);
        }

        if ($certificate->isPending()) {
            throw ValidationException::withMessages([
                'certificate' => 'Wait for the SSL operation to finish before deleting this certificate.',
            ]);
        }

        if (! $certificate->isActive()) {
            $certificate->delete();

            return null;
        }

        $this->assertSiteReadyForSsl($site);

        $args = $this->sslRecipeArguments($site, $certificate);

        return $this->dispatchSslOperation($user, $site, $certificate, [
            [
                'name' => 'Remove HTTPS from Nginx',
                'recipe' => 'ssl.deactivate@v1',
                'aftermath' => FinalizeSslAftermath::key(),
                'arguments' => $args,
            ],
        ]);
    }
}
