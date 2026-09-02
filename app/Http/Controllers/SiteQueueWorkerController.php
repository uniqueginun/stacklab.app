<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateQueueWorkerOperation;
use App\Actions\QueueWorkers\QueryQueueWorkerStatus;
use App\Http\Requests\StoreQueueWorkerRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SiteQueueWorkerController extends Controller
{
    public function store(
        StoreQueueWorkerRequest $request,
        Site $site,
        CreateQueueWorkerOperation $create,
    ): RedirectResponse {
        abort_unless($site->isLaravel(), 404);

        $create->handle($request->user(), $site, $request->workerAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Installing the queue worker.'),
        ]);

        return to_route('sites.queues', $site);
    }

    public function status(
        Request $request,
        Site $site,
        QueryQueueWorkerStatus $query,
    ): JsonResponse {
        Gate::authorize('view', $site);
        abort_unless($site->isLaravel(), 404);

        return response()->json(
            $query->handle($request->user(), $site),
        );
    }
}
