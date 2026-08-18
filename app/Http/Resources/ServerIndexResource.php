<?php

namespace App\Http\Resources;

use App\Enums\ConnectionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'provider' => $this->provider,
            'provider_label' => $this->provider === 'digitalocean' ? 'DigitalOcean' : 'Custom VPS',
            'host' => $this->host,
            'connection_status' => $this->connectionStatusValue(),
            'connection_status_label' => $this->connectionStatusLabel(),
        ];
    }

    protected function connectionStatusValue(): string
    {
        return $this->connection_status instanceof ConnectionStatus
            ? $this->connection_status->value
            : (string) $this->connection_status;
    }

    protected function connectionStatusLabel(): string
    {
        $status = $this->connection_status instanceof ConnectionStatus
            ? $this->connection_status
            : ConnectionStatus::from((string) $this->connection_status);

        return $status->label();
    }
}
