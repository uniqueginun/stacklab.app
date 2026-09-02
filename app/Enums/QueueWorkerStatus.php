<?php

namespace App\Enums;

enum QueueWorkerStatus: string
{
    case Pending = 'pending';
    case Installing = 'installing';
    case Installed = 'installed';
    case Updating = 'updating';
    case Restarting = 'restarting';
    case Deleting = 'deleting';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Updating => 'Updating',
            self::Restarting => 'Restarting',
            self::Deleting => 'Deleting',
            self::Failed => 'Failed',
        };
    }

    public function isBusy(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Installing,
            self::Updating,
            self::Restarting,
            self::Deleting,
        ], true);
    }
}
