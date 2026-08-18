<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case UNVERIFIED = 'unverified';
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case CONNECTED = 'connected';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Unverified server',
            self::PENDING_CONFIRMATION => 'Pending confirmation',
            self::CONNECTED => 'Connected',
            self::FAILED => 'Connection failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'text-sl-ink/50',
            self::PENDING_CONFIRMATION => 'text-sl-accent',
            self::CONNECTED => 'text-sl-accent',
            self::FAILED => 'text-sl-error',
        };
    }
}
