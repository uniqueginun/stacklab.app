<?php

namespace App\Support\QueueWorkers;

use App\Models\Site;
use App\Support\SupportedPlatforms;

final class QueueWorkerSettings
{
    public const int MinProcesses = 1;

    public const int MaxProcesses = 10;

    public const int MinSleep = 0;

    public const int MaxSleep = 60;

    public const int MinTimeout = 1;

    public const int MaxTimeout = 86_400;

    public const int MinTries = 0;

    public const int MaxTries = 1_000;

    public const int MinBackoff = 0;

    public const int MaxBackoff = 3_600;

    public const int MinMaxJobs = 0;

    public const int MaxMaxJobs = 1_000_000;

    public const int MinMaxTime = 0;

    public const int MaxMaxTime = 86_400;

    public const int MinStopWait = 1;

    public const int MaxStopWait = 86_400;

    public const string NamePattern = '/^[A-Za-z][A-Za-z0-9_-]{0,62}$/';

    public const string ConnectionPattern = '/^[A-Za-z][A-Za-z0-9_-]{0,62}$/';

    public const string QueueNamePattern = '/^[A-Za-z0-9._-]+$/';

    public const int LogMaxLines = 200;

    public const int LogMaxBytes = 65_536;

    /**
     * @return list<string>
     */
    public const array Connections = ['redis', 'database', 'sync', 'beanstalkd', 'sqs'];

    /**
     * @return array{
     *     connection: string,
     *     queue: string,
     *     processes: int,
     *     sleep: int,
     *     timeout: int,
     *     tries: int,
     *     backoff: int,
     *     max_jobs: int,
     *     max_time: int,
     *     stopwaitsecs: int,
     *     restart_on_deploy: bool
     * }
     */
    public static function defaults(): array
    {
        return [
            'connection' => 'redis',
            'queue' => 'default',
            'processes' => 1,
            'sleep' => 3,
            'timeout' => 90,
            'tries' => 3,
            'backoff' => 0,
            'max_jobs' => 0,
            'max_time' => 0,
            'stopwaitsecs' => 3600,
            'restart_on_deploy' => true,
        ];
    }

    /**
     * @return list<string>
     */
    public static function phpVersionsFor(Site $site): array
    {
        $site->loadMissing('server');

        $versions = [];

        foreach ([$site->php_version, $site->server->provisionedPhpVersion()] as $version) {
            if (is_string($version) && preg_match('/^\d+\.\d+$/', $version) === 1) {
                $versions[$version] = $version;
            }
        }

        if ($versions !== []) {
            return array_values($versions);
        }

        return SupportedPlatforms::phpVersionsFor($site->server);
    }
}
