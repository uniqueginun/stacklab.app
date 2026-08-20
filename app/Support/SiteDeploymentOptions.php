<?php

namespace App\Support;

final class SiteDeploymentOptions
{
    /**
     * @return array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }
     */
    public static function defaults(): array
    {
        return [
            'run_composer' => true,
            'run_npm' => true,
            'run_migrations' => true,
            'run_caches' => true,
            'run_queue_restart' => false,
            'run_hook' => false,
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }
     */
    public static function normalize(?array $stored): array
    {
        $defaults = self::defaults();

        if ($stored === null) {
            return $defaults;
        }

        return [
            'run_composer' => (bool) ($stored['run_composer'] ?? $defaults['run_composer']),
            'run_npm' => (bool) ($stored['run_npm'] ?? $defaults['run_npm']),
            'run_migrations' => (bool) ($stored['run_migrations'] ?? $defaults['run_migrations']),
            'run_caches' => (bool) ($stored['run_caches'] ?? $defaults['run_caches']),
            'run_queue_restart' => (bool) ($stored['run_queue_restart'] ?? $defaults['run_queue_restart']),
            'run_hook' => (bool) ($stored['run_hook'] ?? $defaults['run_hook']),
        ];
    }

    /**
     * @param  array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }  $options
     * @return array{
     *     run_composer: string,
     *     run_npm: string,
     *     run_migrations: string,
     *     run_caches: string,
     *     run_queue_restart: string,
     *     run_hook: string
     * }
     */
    public static function toRecipeArguments(array $options): array
    {
        return [
            'run_composer' => $options['run_composer'] ? '1' : '0',
            'run_npm' => $options['run_npm'] ? '1' : '0',
            'run_migrations' => $options['run_migrations'] ? '1' : '0',
            'run_caches' => $options['run_caches'] ? '1' : '0',
            'run_queue_restart' => $options['run_queue_restart'] ? '1' : '0',
            'run_hook' => $options['run_hook'] ? '1' : '0',
        ];
    }
}
