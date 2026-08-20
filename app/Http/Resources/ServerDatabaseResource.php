<?php

namespace App\Http\Resources;

use App\Models\ServerDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServerDatabase
 */
class ServerDatabaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->makeVisible('password');

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'password' => $this->password,
            'status' => $this->status,
            'failure_message' => $this->failure_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
