<?php

namespace App\Ssh;

use App\Models\Server;

readonly class HostFingerprint
{
    public function __construct(
        public string $key,
        public string $fingerprint,
    ) {}

    public static function fromServer(Server $server): self
    {
        return new self($server->host_key, $server->host_key_fingerprint);
    }
}
