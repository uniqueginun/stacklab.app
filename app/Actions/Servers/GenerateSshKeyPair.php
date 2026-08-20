<?php

namespace App\Actions\Servers;

class GenerateSshKeyPair
{
    private const string KeyType = 'ssh-ed25519';

    /** @return array{public_key: string, private_key: string} */
    public function handle(string $comment = 'mini-forge-management'): array
    {
        $keyPair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $privateKey = sodium_crypto_sign_secretkey($keyPair);
        $publicBlob = $this->sshString(self::KeyType).$this->sshString($publicKey);

        return [
            'public_key' => self::KeyType.' '.base64_encode($publicBlob).' '.$comment,
            'private_key' => $this->openSshPrivateKey($publicBlob, $publicKey, $privateKey, $comment),
        ];
    }

    private function openSshPrivateKey(string $publicBlob, string $publicKey, string $privateKey, string $comment): string
    {
        $check = random_bytes(4);
        $privateBlock = $check.$check
            .$this->sshString(self::KeyType)
            .$this->sshString($publicKey)
            .$this->sshString($privateKey)
            .$this->sshString($comment);

        $paddingLength = 8 - (strlen($privateBlock) % 8);

        for ($byte = 1; $byte <= $paddingLength; $byte++) {
            $privateBlock .= chr($byte);
        }

        $encoded = base64_encode(
            "openssh-key-v1\0"
            .$this->sshString('none')
            .$this->sshString('none')
            .$this->sshString('')
            .pack('N', 1)
            .$this->sshString($publicBlob)
            .$this->sshString($privateBlock),
        );

        return "-----BEGIN OPENSSH PRIVATE KEY-----\n"
            .chunk_split($encoded, 70, "\n")
            ."-----END OPENSSH PRIVATE KEY-----\n";
    }

    private function sshString(string $value): string
    {
        return pack('N', strlen($value)).$value;
    }
}
