<?php

namespace App\Http\Resources;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Server
 */
class SiteCreateServerResource extends JsonResource
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

        return [
            'uuid' => $server->uuid,
            'name' => $server->name,
            'host' => $server->host,
            'os_label' => $server->osLabel(),
        ];
    }
}
