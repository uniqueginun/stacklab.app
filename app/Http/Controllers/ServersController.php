<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServerStoreRequest;
use App\Http\Resources\ServerIndexResource;
use App\Http\Resources\ServerShowResource;
use App\Models\Server;
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
    public function show(Server $server): Response
    {
        abort_unless($server->user_id === auth()->id(), 403);

        $candidate = session()->get(ServerSSHController::sessionKey($server));
        $fingerprint = is_array($candidate) && is_string($candidate['fingerprint'] ?? null)
            ? $candidate['fingerprint']
            : null;
        $hostKeyType = is_array($candidate) && is_string($candidate['key'] ?? null)
            ? str($candidate['key'])->before(' ')->toString()
            : null;

        return inertia('servers/Show', [
            'server' => (new ServerShowResource($server))->resolve(),
            'sshFingerprint' => $fingerprint,
            'sshHostKeyType' => $hostKeyType,
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
