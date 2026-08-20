<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateRollbackOperation;
use App\Http\Requests\StoreSiteRollbackRequest;
use App\Models\Release;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteRollbackController extends Controller
{
    public function store(
        StoreSiteRollbackRequest $request,
        Site $site,
        Release $release,
        CreateRollbackOperation $createRollbackOperation,
    ): RedirectResponse {
        $createRollbackOperation->handle($request->user(), $site, $release);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Rollback started.',
        ]);

        return to_route('sites.deployments', $site);
    }
}
