<?php

namespace App\Support;

use App\Models\Server;

final class SupportedPlatforms
{
    public const DEFAULT_PHP = '8.4';

    /**
     * PHP versions installable via distro packages or Ondřej/Sury.
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

        return self::PHP[strtolower($os)][self::normalizeVersion($version)] ?? [];
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
        $phpVersions = self::phpVersions($os, $version);

        if ($os === null || $version === null || $phpVersions === []) {
            return null;
        }

        return sprintf(
            'Available on %s %s: %s.',
            ucfirst($os),
            $version,
            implode(', ', $phpVersions),
        );
    }

    public static function supportedLabel(): string
    {
        $labels = [];

        foreach (self::PHP as $os => $releases) {
            $labels[] = ucfirst($os).' '.implode(', ', array_keys($releases));
        }

        return implode(' or ', $labels);
    }

    public static function normalizeVersion(string $version): string
    {
        if (preg_match('/^(\d+)\.0$/', $version, $matches) === 1) {
            return $matches[1];
        }

        return $version;
    }
}
