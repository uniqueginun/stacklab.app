<?php

namespace App\Ssh;

readonly class HostkeyParser
{
    public function parse(string $output): ?HostFingerprint
    {
        $keys = collect(preg_split('/\R/', $output) ?: [])
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, '#'))
            ->map(fn (string $line): array => preg_split('/\s+/', trim($line), 3) ?: [])
            ->filter(fn (array $parts): bool => count($parts) === 3 && in_array($parts[1], ['ssh-ed25519', 'ecdsa-sha2-nistp256', 'ssh-rsa'], true));

        /** @var array{0: string, 1: string, 2: string}|null $parts */
        $parts = $keys->first(fn (array $parts): bool => $parts[1] === 'ssh-ed25519') ?? $keys->first();

        if ($parts === null || base64_decode($parts[2], true) === false) {
            return null;
        }

        $binaryKey = base64_decode($parts[2], true);

        return new HostFingerprint(
            $parts[1].' '.$parts[2],
            'SHA256:'.rtrim(base64_encode(hash('sha256', $binaryKey, true)), '='),
        );
    }
}