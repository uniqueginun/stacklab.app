<?php

namespace App\Http\Resources;

use App\Enums\SiteStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SitesShowResource extends SitesIndexResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $release = $this->currentRelease;
        $data['server']['host'] = $this->server->host;
        $data['is_laravel'] = $this->resource->isLaravel();
        $data['is_php'] = $this->resource->isPhp();
        $data['deployment_options'] = $this->resource->isLaravel()
            ? $this->resource->resolvedDeploymentOptions()
            : null;
        $data['php_version'] = $this->php_version;
        $data['root_path'] = $this->root_path;
        $data['last_deployed_at'] = $this->last_deployed_at?->toIso8601String();
        $data['created_at'] = $this->created_at?->toIso8601String();
        $data['current_release'] = $release === null
            ? null
            : [
                'uuid' => $release->uuid,
                'commit_sha' => $release->commit_sha,
                'short_sha' => Str::substr($release->commit_sha, 0, 7),
                'commit_message' => $release->commit_message,
            ];
        $data['can_manage_ssl'] = $this->statusValue() === SiteStatus::DEPLOYED->value;
        $data['has_active_ssl'] = $this->resource->hasActiveSsl();
        $data['can_include_www'] = ! str_starts_with(strtolower((string) $this->domain), 'www.');

        return $data;
    }
}
