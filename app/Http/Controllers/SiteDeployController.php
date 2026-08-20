<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateDeployOperation;
use App\Http\Requests\StoreSiteDeploymentRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteDeployController extends Controller
{
    public function store(
        StoreSiteDeploymentRequest $request,
        Site $site,
        CreateDeployOperation $createDeployOperation,
    ): RedirectResponse {
        $createDeployOperation->handle(
            $request->user(),
            $site,
            $request->deploymentOptions(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Deployment started.',
        ]);

        return to_route('sites.deployments', $site);
    }
}
