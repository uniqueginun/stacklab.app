<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ServerShowResource extends ServerIndexResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'ssh_port' => (string) $this->ssh_port,
            'ssh_user' => $this->ssh_user,
            'ssh_public_key' => $this->ssh_public_key,
            'is_connected' => $this->isConnected(),
        ];
    }
}
