<?php

namespace App\Support;

final class Pem
{
    public static function isCertificate(string $value): bool
    {
        return self::normalizeCertificates($value) !== null;
    }

    public static function isPrivateKey(string $value): bool
    {
        return self::normalizePrivateKey($value) !== null;
    }

    /**
     * Rewrite certificate PEM so Nginx can load it.
     *
     * Namecheap (and Windows) downloads often use CRLF, unwrapped base64, or
     * omit the newline between concatenated certificates. OpenSSL's x509
     * parser accepts that; Nginx's PEM_read_bio_X509_AUX does not
     * ("bad end line").
     */
    public static function normalizeCertificates(string $value): ?string
    {
        $normalized = self::normalizeBlocks($value, ['CERTIFICATE', 'TRUSTED CERTIFICATE'], 'CERTIFICATE');

        return $normalized === [] ? null : implode("\n", $normalized)."\n";
    }

    public static function normalizePrivateKey(string $value): ?string
    {
        $normalized = self::normalizeBlocks($value, [
            'PRIVATE KEY',
            'RSA PRIVATE KEY',
            'EC PRIVATE KEY',
        ]);

        return $normalized === [] ? null : implode("\n", $normalized)."\n";
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private static function normalizeBlocks(string $value, array $labels, ?string $emitLabel = null): array
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        $alternation = implode('|', array_map(
            fn (string $label): string => preg_quote($label, '/'),
            $labels,
        ));

        if (preg_match_all(
            '/-----BEGIN ('.$alternation.')-----\s*([A-Za-z0-9+\/=\s]+?)\s*-----END \1-----/',
            $value,
            $matches,
            PREG_SET_ORDER,
        ) < 1) {
            return [];
        }

        $blocks = [];

        foreach ($matches as $match) {
            $label = $emitLabel ?? $match[1];
            $body = preg_replace('/\s+/', '', $match[2]) ?? '';

            if ($body === '') {
                return [];
            }

            $wrapped = trim(chunk_split($body, 64, "\n"));
            $blocks[] = "-----BEGIN {$label}-----\n{$wrapped}\n-----END {$label}-----";
        }

        return $blocks;
    }
}
