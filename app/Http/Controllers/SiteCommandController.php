<?php

namespace App\Http\Controllers;

use App\Actions\Sites\RunSiteCommand;
use App\Http\Requests\RunSiteCommandRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class SiteCommandController extends Controller
{
    public function store(
        RunSiteCommandRequest $request,
        Site $site,
        RunSiteCommand $runSiteCommand,
    ): JsonResponse {
        abort_unless($site->isLaravel(), 404);

        return response()->json(
            $runSiteCommand->handle(
                $request->user(),
                $site,
                $request->command(),
            ),
        );
    }
}
