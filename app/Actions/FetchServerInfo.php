<?php

namespace App\Actions;

use App\Models\Server;
use App\Ssh\HostFingerprint;
use App\Ssh\SshService;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FetchServerInfo
{
    public function __construct(private readonly SshService $sshService) {}

    public function handle(Server $server): bool
    {
        $path = resource_path('recipes/server_info.sh');

        $libraryPath = resource_path('recipes/_lib.sh');

        $script = File::get($libraryPath).PHP_EOL.File::get($path);

        $host = HostFingerprint::fromServer($server);

        $result = $this->sshService->run($server, $host, $script);

        if (! $result->successful()) {
            throw new RuntimeException("Failed to fetch server info: {$result->errorOutput}");
        }

        return $server->forceFill(['server_info' => $result->data()])->save();
    }
}
