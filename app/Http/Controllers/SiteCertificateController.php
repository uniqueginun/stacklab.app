<?php

namespace App\Http\Controllers;

use App\Actions\Operations\DeactivateSiteCertificate;
use App\Http\Requests\DestroySiteCertificateRequest;
use App\Models\Site;
use App\Models\SiteCertificate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteCertificateController extends Controller
{
    public function destroy(
        DestroySiteCertificateRequest $request,
        Site $site,
        SiteCertificate $certificate,
        DeactivateSiteCertificate $deactivate,
    ): RedirectResponse {
        $operation = $deactivate->handle($request->user(), $site, $certificate);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $operation === null
                ? 'Certificate removed.'
                : 'Removing HTTPS from the site.',
        ]);

        return to_route('sites.ssl', $site);
    }
}
