<?php

namespace App\Http\Controllers;

use App\Actions\Sites\ReadSiteEnvironment;
use App\Actions\Sites\UpdateSiteEnvironment;
use App\Enums\SiteStatus;
use App\Http\Requests\UpdateSiteEnvironmentRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SiteEnvironmentController extends Controller
{
    public function edit(
        Request $request,
        Site $site,
        ReadSiteEnvironment $readSiteEnvironment,
    ): JsonResponse {
        Gate::authorize('update', $site);
        abort_unless($site->isPhp(), 404);

        $path = $site->environmentFilePath();

        if ($site->status !== SiteStatus::DEPLOYED) {
            return response()->json([
                'contents' => null,
                'path' => $path,
            ]);
        }

        return response()->json([
            'contents' => $readSiteEnvironment->handle($request->user(), $site),
            'path' => $path,
        ]);
    }

    public function update(
        UpdateSiteEnvironmentRequest $request,
        Site $site,
        UpdateSiteEnvironment $updateSiteEnvironment,
    ): RedirectResponse {
        abort_unless($site->isPhp(), 404);

        $updateSiteEnvironment->handle(
            $request->user(),
            $site,
            $request->string('contents')->toString(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Environment file saved.',
        ]);

        return to_route('sites.environment', $site);
    }
}
