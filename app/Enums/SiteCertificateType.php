<?php

namespace App\Enums;

enum SiteCertificateType: string
{
    case LETS_ENCRYPT = 'letsencrypt';
    case EXISTING = 'existing';
    case CSR = 'csr';

    public function label(): string
    {
        return match ($this) {
            self::LETS_ENCRYPT => "Let's Encrypt",
            self::EXISTING => 'Existing certificate',
            self::CSR => 'Certificate signing request',
        };
    }
}
