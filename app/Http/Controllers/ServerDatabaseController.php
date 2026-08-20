<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateServerDatabase;
use App\Http\Requests\StoreServerDatabaseRequest;
use App\Http\Resources\OperationResource;
use App\Http\Resources\ServerDatabaseResource;
use App\Http\Resources\ServerShowResource;
use App\Http\Resources\SitesIndexResource;
use App\Models\Server;
use App\Models\Site;
use App\Support\ProvisioningProfiles;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServerDatabaseController extends Controller
{
    public function index(Server $server, ProvisioningProfiles $profiles): Response
    {
        abort_unless($server->user_id === auth()->id(), 403);

        return $this->render($server, $profiles);
    }

    public function store(
        StoreServerDatabaseRequest $request,
        Server $server,
        CreateServerDatabase $create,
    ): RedirectResponse {
        $create->handle($request->user(), $server, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Creating the database. Reveal the generated password on this page to copy it.'),
        ]);

        return to_route('servers.databases', $server);
    }

    private function render(Server $server, ProvisioningProfiles $profiles): Response
    {
        $candidate = session()->get(ServerSSHController::sessionKey($server));
        $fingerprint = is_array($candidate) && is_string($candidate['fingerprint'] ?? null)
            ? $candidate['fingerprint']
            : null;
        $hostKeyType = is_array($candidate) && is_string($candidate['key'] ?? null)
            ? str($candidate['key'])->before(' ')->toString()
            : null;

        $operation = $server->operations()
            ->where('type', 'create_database')
            ->with(['steps' => fn ($query) => $query->orderBy('position')])
            ->latest()
            ->first();

        $sites = $server->sites()->latest()->get();
        $sites->each(fn (Site $site) => $site->setRelation('server', $server));

        $databases = $server->databases()->latest()->get();

        return inertia('servers/Show', [
            'server' => (new ServerShowResource($server))->resolve(),
            'profiles' => $profiles->options(),
            'operation' => $operation !== null
                ? (new OperationResource($operation))->resolve()
                : null,
            'sshFingerprint' => $fingerprint,
            'sshHostKeyType' => $hostKeyType,
            'sites' => SitesIndexResource::collection($sites)->resolve(),
            'tab' => 'databases',
            'databases' => ServerDatabaseResource::collection($databases)->resolve(),
        ]);
    }
}
