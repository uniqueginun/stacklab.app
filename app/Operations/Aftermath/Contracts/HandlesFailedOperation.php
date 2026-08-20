<?php

namespace App\Operations\Aftermath\Contracts;

use App\Models\Operation;

interface HandlesFailedOperation
{
    public function failed(Operation $operation, ?string $message): void;
}
