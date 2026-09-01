<?php

namespace App\Http\Controllers;

use App\Actions\Operations\InstallExistingCertificate;
use App\Http\Requests\InstallExistingCertificateRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteExistingCertificateController extends Controller
{
    public function store(
        InstallExistingCertificateRequest $request,
        Site $site,
        InstallExistingCertificate $install,
    ): RedirectResponse {
        $install->handle($request->user(), $site, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Installing the existing certificate.',
        ]);

        return to_route('sites.ssl', $site);
    }
}
