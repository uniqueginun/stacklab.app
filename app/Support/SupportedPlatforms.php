<?php

namespace App\Support;

use App\Models\Server;

final class SupportedPlatforms
{
    public const DEFAULT_PHP = '8.4';

    /**
     * PHP versions installable via distro packages, Ondřej/Sury, or Remi.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const PHP = [
        'ubuntu' => [
            '22.04' => ['8.1', '8.2', '8.3', '8.4'],
            '24.04' => ['8.1', '8.2', '8.3', '8.4'],
            '26.04' => ['8.5'],
        ],
        'debian' => [
            '12' => ['8.1', '8.2', '8.3', '8.4'],
            '13' => ['8.2', '8.3', '8.4'],
        ],
        'ol' => [
            '9' => ['8.1', '8.2', '8.3', '8.4'],
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const OS_LABELS = [
        'ubuntu' => 'Ubuntu',
        'debian' => 'Debian',
        'ol' => 'Oracle Linux',
    ];

    /** @return list<string> */
    public static function catalog(): array
    {
        $versions = [];

        foreach (self::PHP as $releases) {
            foreach ($releases as $phpVersions) {
                foreach ($phpVersions as $version) {
                    $versions[$version] = $version;
                }
            }
        }

        ksort($versions, SORT_NATURAL);

        return array_values($versions);
    }

    /** @return list<string> */
    public static function preflightPairs(): array
    {
        $pairs = [];

        foreach (self::PHP as $os => $releases) {
            foreach (array_keys($releases) as $version) {
                $pairs[] = $os.'-'.$version;
            }
        }

        return $pairs;
    }

    public static function supports(?string $os, ?string $version): bool
    {
        return self::phpVersions($os, $version) !== [];
    }

    /**
     * @return list<string>
     */
    public static function phpVersions(?string $os, ?string $version): array
    {
        if ($os === null || $version === null || $os === '' || $version === '') {
            return [];
        }

        $os = strtolower($os);
        $normalized = self::normalizeVersion($version, $os);

        return self::PHP[$os][$normalized] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function phpVersionsFor(?Server $server): array
    {
        if ($server === null) {
            return self::catalog();
        }

        $os = $server->osId();
        $version = $server->osVersion();

        if ($os === null || $version === null) {
            return self::catalog();
        }

        return self::phpVersions($os, $version);
    }

    public static function defaultPhpVersion(?string $os, ?string $version): string
    {
        $versions = self::phpVersions($os, $version);

        if ($versions === []) {
            return self::DEFAULT_PHP;
        }

        if (in_array(self::DEFAULT_PHP, $versions, true)) {
            return self::DEFAULT_PHP;
        }

        return $versions[array_key_last($versions)];
    }

    public static function defaultPhpVersionFor(?Server $server): string
    {
        if ($server === null) {
            return self::DEFAULT_PHP;
        }

        return self::defaultPhpVersion($server->osId(), $server->osVersion());
    }

    public static function hintFor(?Server $server): ?string
    {
        if ($server === null) {
            return null;
        }

        $os = $server->osId();
        $version = $server->osVersion();

        if ($os === null || $version === null) {
            return null;
        }

        $phpVersions = self::phpVersions($os, $version);

        if ($phpVersions === []) {
            return sprintf(
                'PHP provisioning is not available on %s %s. Supported: %s.',
                self::osDisplayName($os),
                $version,
                self::supportedLabel(),
            );
        }

        return sprintf(
            'Available on %s %s: %s.',
            self::osDisplayName($os),
            $version,
            implode(', ', $phpVersions),
        );
    }

    public static function supportedLabel(): string
    {
        $labels = [];

        foreach (self::PHP as $os => $releases) {
            $labels[] = self::osDisplayName($os).' '.implode(', ', array_keys($releases));
        }

        return implode(' or ', $labels);
    }

    public static function osDisplayName(string $os): string
    {
        return self::OS_LABELS[strtolower($os)] ?? ucfirst($os);
    }

    public static function normalizeVersion(string $version, ?string $os = null): string
    {
        if ($os !== null && self::usesMajorVersionCatalog(strtolower($os))) {
            if (preg_match('/^(\d+)/', $version, $matches) === 1) {
                return $matches[1];
            }
        }

        if (preg_match('/^(\d+)\.0$/', $version, $matches) === 1) {
            return $matches[1];
        }

        return $version;
    }

    private static function usesMajorVersionCatalog(string $os): bool
    {
        return $os === 'ol';
    }
}
