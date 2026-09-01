<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Models\SiteCertificate;
use App\Support\Pem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class InstallSignedCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');
        $certificate = $this->route('certificate');

        return $site instanceof Site
            && $certificate instanceof SiteCertificate
            && ($this->user()?->can('update', $site) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if (is_string($this->certificate)) {
            $normalized = Pem::normalizeCertificates($this->certificate);

            if ($normalized !== null) {
                $payload['certificate'] = $normalized;
            }
        }

        if (is_string($this->chain) && $this->chain !== '') {
            $normalized = Pem::normalizeCertificates($this->chain);

            if ($normalized !== null) {
                $payload['chain'] = $normalized;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'certificate' => ['required', 'string', 'max:65535', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Pem::isCertificate($value)) {
                    $fail('The certificate must be a PEM-encoded certificate.');
                }
            }],
            'chain' => ['nullable', 'string', 'max:65535', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! is_string($value) || ! Pem::isCertificate($value)) {
                    $fail('The certificate chain must be PEM-encoded certificates.');
                }
            }],
        ];
    }
}
