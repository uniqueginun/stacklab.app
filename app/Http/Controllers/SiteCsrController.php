<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateCertificateSigningRequest;
use App\Http\Requests\CreateCertificateSigningRequestRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SiteCsrController extends Controller
{
    public function store(
        CreateCertificateSigningRequestRequest $request,
        Site $site,
        CreateCertificateSigningRequest $create,
    ): RedirectResponse {
        $payload = $request->validated();
        $payload['country'] = Str::upper($payload['country']);

        $create->handle($request->user(), $site, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Generating the certificate signing request.',
        ]);

        return to_route('sites.ssl', $site);
    }
}
