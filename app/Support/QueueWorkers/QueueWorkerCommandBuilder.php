<?php

namespace App\Support\QueueWorkers;

use InvalidArgumentException;

final class QueueWorkerCommandBuilder
{
    /**
     * @param  array{
     *     connection: string,
     *     queue: string,
     *     php_version: string,
     *     sleep: int,
     *     timeout: int,
     *     tries: int,
     *     backoff: int,
     *     max_jobs: int,
     *     max_time: int,
     *     artisan_path: string
     * }  $options
     */
    public function build(array $options): string
    {
        $phpBinary = $this->phpBinaryPath($options['php_version']);
        $artisan = $this->absolutePath($options['artisan_path'], 'artisan_path');
        $connection = $this->connection($options['connection']);
        $queue = $this->queueList($options['queue']);

        $arguments = [
            $phpBinary,
            $artisan,
            'queue:work',
            $connection,
            '--queue='.$queue,
            '--sleep='.$this->boundedInteger($options['sleep'], QueueWorkerSettings::MinSleep, QueueWorkerSettings::MaxSleep, 'sleep'),
            '--tries='.$this->boundedInteger($options['tries'], QueueWorkerSettings::MinTries, QueueWorkerSettings::MaxTries, 'tries'),
            '--timeout='.$this->boundedInteger($options['timeout'], QueueWorkerSettings::MinTimeout, QueueWorkerSettings::MaxTimeout, 'timeout'),
        ];

        $backoff = $this->boundedInteger($options['backoff'], QueueWorkerSettings::MinBackoff, QueueWorkerSettings::MaxBackoff, 'backoff');

        if ($backoff > 0) {
            $arguments[] = '--backoff='.$backoff;
        }

        $maxJobs = $this->boundedInteger($options['max_jobs'], QueueWorkerSettings::MinMaxJobs, QueueWorkerSettings::MaxMaxJobs, 'max_jobs');

        if ($maxJobs > 0) {
            $arguments[] = '--max-jobs='.$maxJobs;
        }

        $maxTime = $this->boundedInteger($options['max_time'], QueueWorkerSettings::MinMaxTime, QueueWorkerSettings::MaxMaxTime, 'max_time');

        if ($maxTime > 0) {
            $arguments[] = '--max-time='.$maxTime;
        }

        return implode(' ', array_map(escapeshellarg(...), $arguments));
    }

    public function phpBinaryPath(string $version): string
    {
        if (preg_match('/^\d+\.\d+$/', $version) !== 1) {
            throw new InvalidArgumentException('The PHP version is invalid.');
        }

        return '/usr/bin/php'.$version;
    }

    private function connection(string $connection): string
    {
        if (preg_match(QueueWorkerSettings::ConnectionPattern, $connection) !== 1) {
            throw new InvalidArgumentException('The queue connection is invalid.');
        }

        return $connection;
    }

    private function queueList(string $queue): string
    {
        $names = array_values(array_filter(
            array_map(trim(...), explode(',', $queue)),
            fn (string $name): bool => $name !== '',
        ));

        if ($names === []) {
            throw new InvalidArgumentException('The queue list is invalid.');
        }

        foreach ($names as $name) {
            if (preg_match(QueueWorkerSettings::QueueNamePattern, $name) !== 1) {
                throw new InvalidArgumentException('The queue list is invalid.');
            }
        }

        return implode(',', $names);
    }

    private function absolutePath(string $path, string $field): string
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, "\0") || str_contains($path, '..')) {
            throw new InvalidArgumentException("The {$field} is invalid.");
        }

        return $path;
    }

    private function boundedInteger(int $value, int $min, int $max, string $field): int
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("The {$field} is invalid.");
        }

        return $value;
    }
}
