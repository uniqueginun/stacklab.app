<?php

namespace App\Http\Controllers;

use App\Actions\FetchServerInfo;
use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

class ServerSSHController extends Controller
{
    public function __construct(private SshService $sshService) {}

    public static function sessionKey(Server $server): string
    {
        return 'servers.connection.'.$server->uuid;
    }

    public function connect(Request $request, Server $server)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        if (! $server->canStartSshSetup()) {
            abort(400, 'Server is not ready to connect or already connected.');
        }

        try {
            $server->update(array_merge($this->sshService->generateKeyPair($server), [
                'connection_status' => ConnectionStatus::UNVERIFIED,
                'host_key' => null,
                'host_key_fingerprint' => null,
                'verified_at' => null,
            ]));

            session()->forget(self::sessionKey($server));

            return to_route('servers.show', $server);
        } catch (RuntimeException $e) {
            $server->update(['connection_status' => ConnectionStatus::FAILED]);

            return back()->withErrors(['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $server->update(['connection_status' => ConnectionStatus::FAILED]);

            return back()->withErrors(['message' => 'Unable to generate the SSH key. Please try again.']);
        }
    }

    public function verify(Request $request, Server $server)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        if ($server->isConnected() || ! $server->hasSshKeyPair()) {
            abort(400, 'Generate the management key before verifying the server.');
        }

        try {
            $hostFingerprint = $this->sshService->discoverHost($server);

            session()->put(self::sessionKey($server), [
                'key' => $hostFingerprint->key,
                'fingerprint' => $hostFingerprint->fingerprint,
            ]);

            $server->update([
                'connection_status' => ConnectionStatus::PENDING_CONFIRMATION,
            ]);

            return to_route('servers.show', $server);
        } catch (RuntimeException $e) {
            $server->update(['connection_status' => ConnectionStatus::FAILED]);

            return back()->withErrors(['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $server->update(['connection_status' => ConnectionStatus::FAILED]);

            return back()->withErrors(['message' => 'Unable to read the server fingerprint. Please try again.']);
        }
    }

    public function confirm(Request $request, Server $server, FetchServerInfo $fetchServerInfo)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        $candidate = session()->get(
            $key = self::sessionKey($server)
        );

        if (! is_array($candidate) || ! is_string($candidate['key'] ?? null) || ! is_string($candidate['fingerprint'] ?? null)) {
            return back()->withErrors(['connection' => 'Discover the server fingerprint again before confirming it.']);
        }

        $host = new HostFingerprint($candidate['key'], $candidate['fingerprint']);

        try {
            $this->sshService->verifyConnection($server, $host);
        } catch (RuntimeException $e) {
            $server->update(['connection_status' => ConnectionStatus::FAILED]);

            return back()->withErrors(['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $server->update(['connection_status' => ConnectionStatus::FAILED]);

            return back()->withErrors(['message' => 'Unable to verify the SSH connection. Please try again.']);
        }

        $server->update([
            'host_key' => $host->key,
            'host_key_fingerprint' => $host->fingerprint,
            'connection_status' => ConnectionStatus::CONNECTED,
            'verified_at' => now(),
        ]);

        $fetchServerInfo->handle($server->refresh());

        session()->forget($key);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SSH connection verified.')]);

        return to_route('servers.show', $server);
    }
}
