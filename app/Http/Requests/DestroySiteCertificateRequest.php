<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Models\SiteCertificate;
use Illuminate\Foundation\Http\FormRequest;

class DestroySiteCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');
        $certificate = $this->route('certificate');

        return $site instanceof Site
            && $certificate instanceof SiteCertificate
            && ($this->user()?->can('update', $site) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
