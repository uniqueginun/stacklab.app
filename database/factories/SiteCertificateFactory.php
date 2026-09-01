<?php

namespace Database\Factories;

use App\Enums\SiteCertificateStatus;
use App\Enums\SiteCertificateType;
use App\Models\Site;
use App\Models\SiteCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteCertificate>
 */
class SiteCertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'site_id' => Site::factory()->state(['domain' => $domain]),
            'type' => SiteCertificateType::LETS_ENCRYPT,
            'status' => SiteCertificateStatus::PENDING,
            'domains' => [$domain],
        ];
    }

    public function letsEncrypt(): static
    {
        return $this->state(fn (): array => [
            'type' => SiteCertificateType::LETS_ENCRYPT,
        ]);
    }

    public function existing(): static
    {
        return $this->state(fn (): array => [
            'type' => SiteCertificateType::EXISTING,
        ]);
    }

    public function csr(): static
    {
        return $this->state(fn (): array => [
            'type' => SiteCertificateType::CSR,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SiteCertificateStatus::ACTIVE,
            'activated_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function awaitingCertificate(): static
    {
        return $this->state(fn (): array => [
            'type' => SiteCertificateType::CSR,
            'status' => SiteCertificateStatus::AWAITING_CERTIFICATE,
            'csr' => "-----BEGIN CERTIFICATE REQUEST-----\nMIIBCSRFAKE\n-----END CERTIFICATE REQUEST-----",
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => SiteCertificateStatus::FAILED,
            'failure_message' => 'Unable to obtain the certificate.',
        ]);
    }
}
