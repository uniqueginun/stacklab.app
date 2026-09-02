<?php

namespace App\Support\QueueWorkers;

use InvalidArgumentException;

final class SupervisorQueueWorkerConfigBuilder
{
    /**
     * @param  array{
     *     program: string,
     *     command: string,
     *     directory: string,
     *     user: string,
     *     processes: int,
     *     stopwaitsecs: int,
     *     stdout_logfile: string
     * }  $options
     */
    public function build(array $options): string
    {
        $program = $this->assertProgramName($options['program']);
        $command = $this->iniValue($options['command'], 'command');
        $directory = $this->absolutePath($options['directory'], 'directory');
        $user = $this->linuxUser($options['user']);
        $logfile = $this->absolutePath($options['stdout_logfile'], 'stdout_logfile');
        $processes = $this->boundedInteger(
            $options['processes'],
            QueueWorkerSettings::MinProcesses,
            QueueWorkerSettings::MaxProcesses,
            'processes',
        );
        $stopWait = $this->boundedInteger(
            $options['stopwaitsecs'],
            QueueWorkerSettings::MinStopWait,
            QueueWorkerSettings::MaxStopWait,
            'stopwaitsecs',
        );

        return implode("\n", [
            "[program:{$program}]",
            'process_name=%(program_name)s_%(process_num)02d',
            'command='.$command,
            'directory='.$directory,
            'user='.$user,
            'numprocs='.$processes,
            'autostart=true',
            'autorestart=true',
            'stopasgroup=true',
            'killasgroup=true',
            'stopwaitsecs='.$stopWait,
            'redirect_stderr=true',
            'stdout_logfile='.$logfile,
            'stdout_logfile_maxbytes=10MB',
            'stdout_logfile_backups=5',
            '',
        ]);
    }

    public function programName(int $siteId, int $workerId): string
    {
        if ($siteId < 1 || $workerId < 1) {
            throw new InvalidArgumentException('The Supervisor program name is invalid.');
        }

        return sprintf('stacklab-site-%d-worker-%d', $siteId, $workerId);
    }

    public function configPath(int $siteId, int $workerId): string
    {
        return '/etc/supervisor/conf.d/'.$this->programName($siteId, $workerId).'.conf';
    }

    public function assertProgramName(string $program): string
    {
        if (preg_match('/^stacklab-site-\d+-worker-\d+$/', $program) !== 1) {
            throw new InvalidArgumentException('The Supervisor program name is invalid.');
        }

        return $program;
    }

    public function logPath(string $rootPath, int $workerId): string
    {
        $root = $this->absolutePath($rootPath, 'root_path');

        if ($workerId < 1) {
            throw new InvalidArgumentException('The worker log path is invalid.');
        }

        return $root.'/shared/logs/worker-'.$workerId.'.log';
    }

    private function linuxUser(string $user): string
    {
        if (preg_match('/^[a-z_][a-z0-9_-]{0,31}$/', $user) !== 1) {
            throw new InvalidArgumentException('The site Linux user is invalid.');
        }

        return $user;
    }

    private function iniValue(string $value, string $field): string
    {
        if ($value === '' || str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, ';')) {
            throw new InvalidArgumentException("The {$field} is invalid.");
        }

        return $value;
    }

    private function absolutePath(string $path, string $field): string
    {
        $value = $this->iniValue($path, $field);

        if (! str_starts_with($value, '/') || str_contains($value, "\0") || str_contains($value, '..')) {
            throw new InvalidArgumentException("The {$field} is invalid.");
        }

        return rtrim($value, '/');
    }

    private function boundedInteger(int $value, int $min, int $max, string $field): int
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("The {$field} is invalid.");
        }

        return $value;
    }
}
