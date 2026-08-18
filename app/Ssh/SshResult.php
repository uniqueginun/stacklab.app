<?php

namespace App\Ssh;

readonly class SshResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput = '',
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function json(): array
    {
        return json_decode($this->output, true);
    }

    public function jsonOrNull(): ?array
    {
        return json_decode($this->output, true) ?: null;
    }

    public function data(): array
    {
        return data_get($this->json(), 'data', []);
    }
}
