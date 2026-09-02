<?php

namespace App\Http\Controllers;

use App\Enums\ConnectionStatus;
use App\Http\Requests\SiteStoreRequest;
use App\Http\Resources\OperationResource;
use App\Http\Resources\QueueWorkerResource;
use App\Http\Resources\ReleaseResource;
use App\Http\Resources\SiteCertificateResource;
use App\Http\Resources\SiteCreateServerResource;
use App\Http\Resources\SitesIndexResource;
use App\Http\Resources\SitesShowResource;
use App\Models\Release;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Support\QueueWorkers\QueueWorkerSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SitesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $sites = $user
            ->sites()
            ->with('server')
            ->latest()
            ->get();

        return inertia('sites/Index', [
            'sites' => SitesIndexResource::collection($sites)->resolve(),
            'can_create_sites' => $this->provisionedServersFor($user)->isNotEmpty(),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $servers = $this->provisionedServersFor($request->user());

        if ($servers->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Provision a connected server before creating a site.'),
            ]);

            return to_route('servers.index');
        }

        $selected = $request->string('server')->toString();

        return inertia('sites/Create', [
            'type' => $request->filled('type') ? $request->string('type')->toString() : null,
            'server' => $servers->firstWhere('uuid', $selected)?->uuid,
            'servers' => SiteCreateServerResource::collection($servers)->resolve(),
        ]);
    }

    public function store(SiteStoreRequest $request): RedirectResponse
    {
        $server = $request->connectedServer();

        abort_unless($server !== null, 422);

        $site = $server->sites()->create(
            $request->safe()->only(['type', 'domain', 'web_directory']) + [
                'user_id' => $request->user()->id,
                'root_path' => Site::rootPathFor($server, $request->string('domain')->toString()),
                'php_version' => $server->provisionedPhpVersion(),
            ]
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Site created successfully.'),
        ]);

        return to_route('sites.show', $site);
    }

    public function show(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);

        return $this->renderShow($request, $site, 'info');
    }

    public function source(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);

        return $this->renderShow($request, $site, 'source');
    }

    public function deployments(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);

        return $this->renderShow($request, $site, 'deployments');
    }

    public function environment(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);
        abort_unless($site->isPhp(), 404);

        return $this->renderShow($request, $site, 'environment');
    }

    public function commands(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);
        abort_unless($site->isLaravel(), 404);

        return $this->renderShow($request, $site, 'commands');
    }

    public function queues(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);
        abort_unless($site->isLaravel(), 404);

        return $this->renderShow($request, $site, 'queues');
    }

    public function ssl(Request $request, Site $site): Response
    {
        Gate::authorize('view', $site);

        return $this->renderShow($request, $site, 'ssl');
    }

    public function destroy(Site $site): RedirectResponse
    {
        Gate::authorize('delete', $site);

        $site->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Site deleted successfully.')]);

        return to_route('sites.index');
    }

    /**
     * @return Collection<int, Server>
     */
    private function provisionedServersFor(User $user): Collection
    {
        return $user->servers()
            ->where('connection_status', ConnectionStatus::CONNECTED)
            ->whereNotNull('profile')
            ->latest()
            ->get()
            ->filter(fn (Server $server) => $server->isConnected() && $server->isProvisioned())
            ->values();
    }

    private function renderShow(Request $request, Site $site, string $tab): Response
    {
        $site->loadMissing(['server', 'currentRelease']);

        $connection = $request->user()->githubConnection;
        $operation = match ($tab) {
            'deployments' => $site->latestDeploymentOperation(),
            'ssl' => $site->latestSslOperation(),
            'queues' => $site->latestQueueWorkerOperation(),
            default => null,
        };
        $releases = $tab === 'deployments'
            ? $site->releases()->latest('id')->get()->each(
                fn (Release $release) => $release->setRelation('site', $site),
            )
            : collect();
        $certificate = $tab === 'ssl' ? $site->displayCertificate() : null;
        $workers = $tab === 'queues'
            ? $site->queueWorkers()->latest('id')->get()
            : collect();

        return inertia('sites/Show', [
            'site' => (new SitesShowResource($site))->resolve(),
            'tab' => $tab,
            'github' => [
                'connected' => $connection !== null,
                'username' => $connection?->username,
            ],
            'operation' => $operation !== null
                ? (new OperationResource($operation))->resolve()
                : null,
            'releases' => ReleaseResource::collection($releases)->resolve(),
            'certificate' => $certificate !== null
                ? (new SiteCertificateResource($certificate))->resolve()
                : null,
            'workers' => QueueWorkerResource::collection($workers)->resolve(),
            'php_versions' => $tab === 'queues' ? QueueWorkerSettings::phpVersionsFor($site) : [],
            'queue_worker_defaults' => $tab === 'queues' ? QueueWorkerSettings::defaults() : null,
        ]);
    }
}
