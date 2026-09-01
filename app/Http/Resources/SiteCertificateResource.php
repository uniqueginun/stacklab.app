<?php

namespace App\Http\Resources;

use App\Enums\SiteCertificateStatus;
use App\Enums\SiteCertificateType;
use App\Models\SiteCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteCertificate
 */
class SiteCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof SiteCertificateType ? $this->type : SiteCertificateType::from((string) $this->type);
        $status = $this->status instanceof SiteCertificateStatus
            ? $this->status
            : SiteCertificateStatus::from((string) $this->status);

        return [
            'uuid' => $this->uuid,
            'type' => $type->value,
            'type_label' => $type->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'domains' => is_array($this->domains) ? $this->domains : [],
            'csr' => $this->csr,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'failure_message' => $this->failure_message,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
