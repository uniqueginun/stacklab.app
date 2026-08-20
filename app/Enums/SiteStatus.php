<?php

namespace App\Enums;

enum SiteStatus: string
{
    case PENDING = 'pending';
    case DEPLOYING = 'deploying';
    case DEPLOYED = 'deployed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::DEPLOYING => 'Deploying',
            self::DEPLOYED => 'Deployed',
            self::FAILED => 'Failed',
        };
    }
}
