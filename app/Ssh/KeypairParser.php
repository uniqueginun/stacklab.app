<?php

namespace App\Ssh;

use RuntimeException;

class KeypairParser
{
    public function parseKeyOutput(string $output, string $error, int $exit_code, bool $success): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = json_decode(trim($output), true);

        if (! is_array($payload)) {
            throw new RuntimeException(
                filled(trim($error))
                    ? trim($error)
                    : 'SSH key setup script returned invalid JSON.'
            );
        }

        if (! $success || $exit_code !== 0 || ($payload['success'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($payload['error'] ?? null) && filled($payload['error'])
                    ? $payload['error']
                    : (filled(trim($error)) ? trim($error) : 'SSH key setup failed.')
            );
        }

        $privateKeyPath = data_get($payload, 'key.private_key_path');
        $publicKey = data_get($payload, 'key.public_key');
        $fingerprint = data_get($payload, 'key.fingerprint');
        $knownHostsPath = data_get($payload, 'known_hosts.path');

        if (
            ! is_string($privateKeyPath) || ! filled($privateKeyPath)
            || ! is_string($publicKey) || ! filled($publicKey)
            || ! is_string($fingerprint) || ! filled($fingerprint)
            || ! is_string($knownHostsPath) || ! filled($knownHostsPath)
        ) {
            throw new RuntimeException('SSH key setup script response was missing required key fields.');
        }

        return [
            'host_key_fingerprint' => $fingerprint,
            'known_hosts_path' => $knownHostsPath,
            'ssh_public_key' => $publicKey,
            'ssh_private_key' => $privateKeyPath,
        ];
    }
}
