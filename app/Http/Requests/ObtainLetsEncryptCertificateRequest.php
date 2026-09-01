<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class ObtainLetsEncryptCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && ($this->user()?->can('update', $site) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_www' => ['sometimes', 'boolean'],
        ];
    }

    public function includeWww(): bool
    {
        return $this->boolean('include_www');
    }
}
