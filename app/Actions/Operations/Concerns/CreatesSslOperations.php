<?php

namespace App\Actions\Operations\Concerns;

use App\Enums\SiteStatus;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Site;
use App\Models\SiteCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait CreatesSslOperations
{
    private function assertSiteReadyForSsl(Site $site): void
    {
        if ($site->status !== SiteStatus::DEPLOYED) {
            throw ValidationException::withMessages([
                'site' => 'Deploy the site before activating HTTPS.',
            ]);
        }
    }

    private function assertNoBlockingCertificate(Site $site): void
    {
        if ($site->inFlightCertificate() !== null) {
            throw ValidationException::withMessages([
                'site' => 'An SSL operation is already in progress for this site.',
            ]);
        }

        if ($site->activeCertificate() !== null) {
            throw ValidationException::withMessages([
                'site' => 'Delete the current certificate before installing a new one.',
            ]);
        }
    }

    /**
     * @param  list<array{name: string, recipe: string, aftermath?: string, arguments: array<string, mixed>}>  $steps
     */
    private function dispatchSslOperation(User $user, Site $site, SiteCertificate $certificate, array $steps): Operation
    {
        $operation = DB::transaction(function () use ($user, $site, $certificate, $steps): Operation {
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->id);
            $server = $lockedSite->server()->lockForUpdate()->firstOrFail();

            if ($server->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'site' => 'This server already has an active operation.',
                ]);
            }

            $operation = Operation::query()->create([
                'uuid' => (string) Str::uuid(),
                'server_id' => $server->id,
                'user_id' => $user->id,
                'type' => 'ssl',
                'status' => 'pending',
                'plan_snapshot' => [
                    'site_id' => $lockedSite->id,
                    'site_public_id' => $lockedSite->uuid,
                    'certificate_id' => $certificate->id,
                    'certificate_uuid' => $certificate->uuid,
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            return $operation;
        }, attempts: 3);

        ProcessOperation::dispatch($operation->getKey());

        return $operation;
    }

    /**
     * @return array<string, mixed>
     */
    private function sslRecipeArguments(Site $site, SiteCertificate $certificate): array
    {
        $site->loadMissing('server');
        $root = $this->nginxConfig->documentRoot($site, useCurrentSymlink: true);
        $domains = is_array($certificate->domains) ? $certificate->domains : [$site->domain];

        return [
            'root_path' => $site->root_path,
            'domain' => $site->domain,
            'site_type' => $site->type,
            'php_version' => $site->php_version,
            'ssh_user' => $site->server->ssh_user,
            'certificate_id' => $certificate->id,
            'ssl_domains' => implode(',', $domains),
            'ssl_certificate_path' => $certificate->certificatePath(),
            'ssl_private_key_path' => $certificate->privateKeyPath(),
            'nginx_http_config_b64' => base64_encode(
                $this->nginxConfig->serverBlock($site, $root, forceHttp: true),
            ),
            'nginx_ssl_config_b64' => base64_encode(
                $this->nginxConfig->serverBlock($site, $root, $certificate),
            ),
        ];
    }
}
