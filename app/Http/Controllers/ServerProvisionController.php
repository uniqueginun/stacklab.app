<?php

namespace App\Http\Controllers;

use App\Actions\Operations\ProvisionOperation;
use App\Http\Requests\ServerProvisionRequest;
use App\Jobs\ProcessOperation;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ServerProvisionController extends Controller
{
    public function __invoke(
        ServerProvisionRequest $request,
        Server $server,
        ProvisionOperation $provision,
    ): RedirectResponse {
        $operation = $provision->handle($request->validated(), $server, $request->user());

        ProcessOperation::dispatch($operation->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Provisioning started.'),
        ]);

        return to_route('servers.show', $server);
    }
}
