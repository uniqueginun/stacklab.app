<?php

namespace App\Http\Controllers;

use App\Actions\Operations\InstallSignedCertificate;
use App\Http\Requests\InstallSignedCertificateRequest;
use App\Models\Site;
use App\Models\SiteCertificate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteSignedCertificateController extends Controller
{
    public function store(
        InstallSignedCertificateRequest $request,
        Site $site,
        SiteCertificate $certificate,
        InstallSignedCertificate $install,
    ): RedirectResponse {
        $install->handle($request->user(), $site, $certificate, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Installing the signed certificate.',
        ]);

        return to_route('sites.ssl', $site);
    }
}
