<?php

namespace App\Http\Resources;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Release
 */
class ReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Release $release */
        $release = $this->resource;
        $site = $release->site;
        $isCurrent = $site !== null && $site->current_release_id === $release->id;

        return [
            'uuid' => $release->uuid,
            'commit_sha' => $release->commit_sha,
            'short_sha' => Str::substr($release->commit_sha, 0, 7),
            'commit_message' => $release->commit_message,
            'status' => $release->status,
            'status_label' => Str::headline(str_replace('_', ' ', $release->status)),
            'activated_at' => $release->activated_at?->toIso8601String(),
            'created_at' => $release->created_at?->toIso8601String(),
            'is_current' => $isCurrent,
            'can_rollback' => $release->canBeRolledBackTo($site?->current_release_id),
        ];
    }
}
