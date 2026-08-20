<?php

namespace App\Http\Resources;

use App\Models\Server;
use App\Support\MysqlVersions;
use App\Support\SupportedPlatforms;
use Illuminate\Http\Request;

/**
 * @mixin Server
 */
class ServerShowResource extends ServerIndexResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Server $server */
        $server = $this->resource;

        return parent::toArray($request) + [
            'ssh_port' => (string) $server->ssh_port,
            'ssh_user' => $server->ssh_user,
            'ssh_public_key' => $server->ssh_public_key,
            'is_connected' => $server->isConnected(),
            'is_provisioned' => $server->isProvisioned(),
            'can_provision' => $server->canProvision(),
            'profile' => $server->profile,
            'os_label' => $server->osLabel(),
            'php_versions' => SupportedPlatforms::phpVersionsFor($server),
            'default_php_version' => SupportedPlatforms::defaultPhpVersionFor($server),
            'php_hint' => SupportedPlatforms::hintFor($server),
            'mysql_versions' => MysqlVersions::all(),
            'default_mysql_version' => MysqlVersions::default(),
            'has_mysql' => $server->hasMysql(),
        ];
    }
}
