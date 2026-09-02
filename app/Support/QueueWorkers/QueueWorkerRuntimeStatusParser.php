<?php

namespace App\Support\QueueWorkers;

use Illuminate\Support\Carbon;

final class QueueWorkerRuntimeStatusParser
{
    /**
     * @return array{
     *     configured_processes: int,
     *     running_processes: int,
     *     states: array<string, int>,
     *     healthy: bool,
     *     checked_at: string,
     *     missing: bool
     * }
     */
    public function parse(string $output, int $configuredProcesses, ?Carbon $checkedAt = null): array
    {
        $states = [];
        $running = 0;
        $missing = $this->isMissing($output);

        if (! $missing) {
            foreach (preg_split('/\R/', $output) ?: [] as $line) {
                $line = trim($line);

                if ($line === '' || ! preg_match('/^\S+\s+(RUNNING|STARTING|STOPPED|STOPPING|FATAL|BACKOFF|EXITED|UNKNOWN)\b/', $line, $matches)) {
                    continue;
                }

                $state = $matches[1];
                $states[$state] = ($states[$state] ?? 0) + 1;

                if ($state === 'RUNNING') {
                    $running++;
                }
            }
        }

        $configured = max(0, $configuredProcesses);

        return [
            'configured_processes' => $configured,
            'running_processes' => $running,
            'states' => $states,
            'healthy' => ! $missing && $configured > 0 && $running === $configured,
            'checked_at' => ($checkedAt ?? now())->toIso8601String(),
            'missing' => $missing || ($states === [] && ! $this->looksLikeStatusListing($output)),
        ];
    }

    /**
     * @param  array<string, int>  $configuredByProgram
     * @return array<string, array{
     *     configured_processes: int,
     *     running_processes: int,
     *     states: array<string, int>,
     *     healthy: bool,
     *     checked_at: string,
     *     missing: bool
     * }>
     */
    public function parseGroups(string $output, array $configuredByProgram, ?Carbon $checkedAt = null): array
    {
        $results = [];

        foreach ($configuredByProgram as $program => $configured) {
            $chunk = $this->chunkForProgram($output, $program);
            $results[$program] = $this->parse($chunk, $configured, $checkedAt);
        }

        return $results;
    }

    private function chunkForProgram(string $output, string $program): string
    {
        $pattern = '/STACKLAB_PROGRAM_BEGIN:'.preg_quote($program, '/').'\R(.*?)\RSTACKLAB_PROGRAM_END:'.preg_quote($program, '/').'/s';

        if (preg_match($pattern, $output, $matches) === 1) {
            return $matches[1];
        }

        return $output;
    }

    private function isMissing(string $output): bool
    {
        $normalized = strtolower($output);

        return str_contains($normalized, 'no such process')
            || str_contains($normalized, 'no such file')
            || str_contains($normalized, 'refused connection')
            || (str_contains($normalized, 'error: ') && ! $this->looksLikeStatusListing($output));
    }

    private function looksLikeStatusListing(string $output): bool
    {
        return preg_match('/^\S+\s+(RUNNING|STARTING|STOPPED|STOPPING|FATAL|BACKOFF|EXITED|UNKNOWN)\b/m', $output) === 1;
    }
}
