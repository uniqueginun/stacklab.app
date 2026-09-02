<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateQueueWorkerOperation;
use App\Actions\Operations\DeleteQueueWorkerOperation;
use App\Actions\Operations\GracefulRestartQueueWorkerOperation;
use App\Actions\Operations\RestartQueueWorkerOperation;
use App\Actions\Operations\UpdateQueueWorkerOperation;
use App\Actions\QueueWorkers\QueryQueueWorkerStatus;
use App\Actions\QueueWorkers\ReadQueueWorkerLogs;
use App\Http\Requests\ManageQueueWorkerRequest;
use App\Http\Requests\StoreQueueWorkerRequest;
use App\Http\Requests\UpdateQueueWorkerRequest;
use App\Models\QueueWorker;
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

    public function update(
        UpdateQueueWorkerRequest $request,
        Site $site,
        QueueWorker $queueWorker,
        UpdateQueueWorkerOperation $update,
    ): RedirectResponse {
        abort_unless($site->isLaravel(), 404);

        $operation = $update->handle(
            $request->user(),
            $site,
            $queueWorker,
            $request->workerAttributes(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $operation === null
                ? __('Queue worker updated.')
                : __('Updating the queue worker.'),
        ]);

        return to_route('sites.queues', $site);
    }

    public function destroy(
        ManageQueueWorkerRequest $request,
        Site $site,
        QueueWorker $queueWorker,
        DeleteQueueWorkerOperation $delete,
    ): RedirectResponse {
        abort_unless($site->isLaravel(), 404);

        $delete->handle($request->user(), $site, $queueWorker);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Removing the queue worker.'),
        ]);

        return to_route('sites.queues', $site);
    }

    public function restart(
        ManageQueueWorkerRequest $request,
        Site $site,
        QueueWorker $queueWorker,
        RestartQueueWorkerOperation $restart,
    ): RedirectResponse {
        abort_unless($site->isLaravel(), 404);

        $restart->handle($request->user(), $site, $queueWorker);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Restarting the queue worker.'),
        ]);

        return to_route('sites.queues', $site);
    }

    public function gracefulRestart(
        ManageQueueWorkerRequest $request,
        Site $site,
        QueueWorker $queueWorker,
        GracefulRestartQueueWorkerOperation $restart,
    ): RedirectResponse {
        abort_unless($site->isLaravel(), 404);

        $restart->handle($request->user(), $site, $queueWorker);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Signaling Laravel queue workers to restart.'),
        ]);

        return to_route('sites.queues', $site);
    }

    public function logs(
        Request $request,
        Site $site,
        QueueWorker $queueWorker,
        ReadQueueWorkerLogs $read,
    ): JsonResponse {
        Gate::authorize('view', $site);
        abort_unless($site->isLaravel(), 404);

        return response()->json(
            $read->handle($request->user(), $site, $queueWorker),
        );
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
