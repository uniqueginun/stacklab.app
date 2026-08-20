<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServerStoreRequest;
use App\Http\Resources\OperationResource;
use App\Http\Resources\ServerIndexResource;
use App\Http\Resources\ServerShowResource;
use App\Http\Resources\SitesIndexResource;
use App\Models\Server;
use App\Models\Site;
use App\Support\ProvisioningProfiles;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $servers = Server::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return inertia('servers/Index', [
            'servers' => ServerIndexResource::collection($servers)->resolve(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return inertia('servers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ServerStoreRequest $request): RedirectResponse
    {
        $server = Server::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server created successfully.')]);

        return to_route('servers.show', $server);
    }

    /**
     * Display the specified resource.
     */
    public function show(Server $server, ProvisioningProfiles $profiles): Response
    {
        abort_unless($server->user_id === auth()->id(), 403);

        $candidate = session()->get(ServerSSHController::sessionKey($server));
        $fingerprint = is_array($candidate) && is_string($candidate['fingerprint'] ?? null)
            ? $candidate['fingerprint']
            : null;
        $hostKeyType = is_array($candidate) && is_string($candidate['key'] ?? null)
            ? str($candidate['key'])->before(' ')->toString()
            : null;

        $operation = $server->operations()
            ->where('type', 'provision')
            ->with(['steps' => fn ($query) => $query->orderBy('position')])
            ->latest()
            ->first();

        $sites = $server->sites()->latest()->get();
        $sites->each(fn (Site $site) => $site->setRelation('server', $server));

        return inertia('servers/Show', [
            'server' => (new ServerShowResource($server))->resolve(),
            'profiles' => $profiles->options(),
            'operation' => $operation !== null
                ? (new OperationResource($operation))->resolve()
                : null,
            'sshFingerprint' => $fingerprint,
            'sshHostKeyType' => $hostKeyType,
            'sites' => SitesIndexResource::collection($sites)->resolve(),
            'tab' => 'overview',
            'databases' => [],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server): RedirectResponse
    {
        abort_unless($server->user_id === auth()->id(), 403);

        $server->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server deleted successfully.')]);

        return to_route('servers.index');
    }
}
