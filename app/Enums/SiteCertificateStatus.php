<?php

namespace App\Enums;

enum SiteCertificateStatus: string
{
    case PENDING = 'pending';
    case AWAITING_CERTIFICATE = 'awaiting_certificate';
    case ACTIVE = 'active';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::AWAITING_CERTIFICATE => 'Awaiting certificate',
            self::ACTIVE => 'Active',
            self::FAILED => 'Failed',
        };
    }
}
