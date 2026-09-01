<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

class CreateCertificateSigningRequestRequest extends FormRequest
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
            'country' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'state' => ['required', 'string', 'max:64'],
            'locality' => ['required', 'string', 'max:64'],
            'organization' => ['required', 'string', 'max:128'],
            'organizational_unit' => ['nullable', 'string', 'max:128'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locality' => 'city',
            'organizational_unit' => 'organizational unit',
        ];
    }
}
