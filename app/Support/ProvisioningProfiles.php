<?php

namespace App\Support;

use InvalidArgumentException;

final class ProvisioningProfiles
{
    private const PHP_EXPECT = 'php,composer,nginx,mysql,redis-server,node';

    private const STATIC_EXPECT = 'nginx';

    private const PROFILES = [
        'php' => [
            ['name' => 'Verify server access', 'recipe' => 'preflight.check@v1', 'arguments' => []],
            ['name' => 'Install PHP', 'recipe' => 'php.install@v1', 'arguments' => []],
            ['name' => 'Install Composer', 'recipe' => 'composer.install@v1', 'arguments' => []],
            ['name' => 'Install Nginx', 'recipe' => 'nginx.install@v1', 'arguments' => []],
            ['name' => 'Install MySQL', 'recipe' => 'mysql.install@v1', 'arguments' => []],
            ['name' => 'Install Redis', 'recipe' => 'redis.install@v1', 'arguments' => []],
            ['name' => 'Install Node.js', 'recipe' => 'node.install@v1', 'arguments' => []],
            ['name' => 'Verify provisioning', 'recipe' => 'profile.verify@v1', 'arguments' => ['expect' => self::PHP_EXPECT]],
        ],

        'static' => [
            ['name' => 'Verify server access', 'recipe' => 'preflight.check@v1', 'arguments' => []],
            ['name' => 'Install Nginx', 'recipe' => 'nginx.install@v1', 'arguments' => []],
            ['name' => 'Verify provisioning', 'recipe' => 'profile.verify@v1', 'arguments' => ['expect' => self::STATIC_EXPECT]],
        ],
    ];

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::PROFILES);
    }

    /**
     * @return list<array{key: string, label: string, description: string, requires_php: bool, requires_mysql: bool}>
     */
    public function options(): array
    {
        return collect($this->keys())
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => match ($key) {
                    'php' => 'PHP',
                    'static' => 'Static',
                    default => ucfirst($key),
                },
                'description' => match ($key) {
                    'php' => 'PHP, Composer, Nginx, MySQL, Redis, and Node.js',
                    'static' => 'Nginx only',
                    default => ucfirst($key),
                },
                'requires_php' => $this->requiresPhp($key),
                'requires_mysql' => $this->requiresMysql($key),
            ])
            ->values()
            ->all();
    }

    public function requiresPhp(string $profile): bool
    {
        foreach ($this->baseSteps($profile) as $step) {
            if ($step['recipe'] === 'php.install@v1') {
                return true;
            }
        }

        return false;
    }

    public function requiresMysql(string $profile): bool
    {
        foreach ($this->baseSteps($profile) as $step) {
            if ($step['recipe'] === 'mysql.install@v1') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     name: string,
     *     recipe: string,
     *     arguments: array<string, scalar>
     * }>
     */
    public function steps(string $profile, ?string $phpVersion = null, ?string $mysqlVersion = null): array
    {
        $normalized = array_map(function (array $step): array {
            return [
                'name' => $step['name'],
                'recipe' => $step['recipe'],
                'arguments' => $step['arguments'] ?? [],
            ];
        }, $this->baseSteps($profile));

        return array_map(function (array $step) use ($phpVersion, $mysqlVersion): array {
            if ($step['recipe'] === 'php.install@v1' && $phpVersion !== null) {
                return [
                    ...$step,
                    'arguments' => [
                        ...$step['arguments'],
                        'php_version' => $phpVersion,
                    ],
                ];
            }

            if ($step['recipe'] === 'mysql.install@v1') {
                return [
                    ...$step,
                    'arguments' => [
                        ...$step['arguments'],
                        'mysql_version' => $mysqlVersion ?? $step['arguments']['mysql_version'] ?? MysqlVersions::default(),
                    ],
                ];
            }

            return $step;
        }, $normalized);
    }

    /**
     * @return list<array{name: string, recipe: string, arguments: array<string, scalar>}>
     */
    private function baseSteps(string $profile): array
    {
        return self::PROFILES[$profile] ?? throw new InvalidArgumentException(
            "Unknown provisioning profile [{$profile}].",
        );
    }
}
