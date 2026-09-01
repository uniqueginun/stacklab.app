<?php

namespace App\Http\Controllers;

use App\Actions\Operations\ObtainLetsEncryptCertificate;
use App\Http\Requests\ObtainLetsEncryptCertificateRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteLetsEncryptController extends Controller
{
    public function store(
        ObtainLetsEncryptCertificateRequest $request,
        Site $site,
        ObtainLetsEncryptCertificate $obtain,
    ): RedirectResponse {
        $obtain->handle($request->user(), $site, $request->includeWww());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Let's Encrypt certificate request started.",
        ]);

        return to_route('sites.ssl', $site);
    }
}
