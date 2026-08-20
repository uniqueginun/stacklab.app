<?php

namespace App\Http\Resources;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SitesIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Site $site */
        $site = $this->resource;

        return [
            'uuid' => $site->uuid,
            'domain' => $site->domain,
            'type' => $site->type,
            'status' => $this->statusValue(),
            'status_label' => $this->statusLabel(),
            'web_directory' => $site->web_directory,
            'repository_url' => $site->repository_url,
            'repository_branch' => $site->repository_branch,
            'server' => [
                'uuid' => $site->server->uuid,
                'name' => $site->server->name,
            ],
        ];
    }

    protected function statusValue(): string
    {
        return $this->status instanceof SiteStatus
            ? $this->status->value
            : (string) $this->status;
    }

    protected function statusLabel(): string
    {
        $status = $this->status instanceof SiteStatus
            ? $this->status
            : SiteStatus::from((string) $this->status);

        return $status->label();
    }
}
