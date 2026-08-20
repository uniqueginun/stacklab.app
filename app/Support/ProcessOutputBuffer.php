<?php

namespace App\Support;

use Closure;

final class ProcessOutputBuffer
{
    private string $completed = '';

    private string $buffer = '';

    private float $lastPersistedAt = 0.0;

    /**
     * @param  Closure(string): void  $persist
     */
    public function __construct(
        private Closure $persist,
        private int $throttleMilliseconds = 150,
    ) {}

    public function ingest(string $chunk): void
    {
        $this->buffer .= $chunk;
        $this->drainCompletedLines();
        $this->maybePersist();
    }

    public function finish(): void
    {
        $this->drainCompletedLines();

        $remainder = $this->displayLine($this->buffer);

        if ($remainder !== '') {
            $this->appendCompleted($remainder);
        }

        $this->buffer = '';
        $this->persistNow();
    }

    public function output(): string
    {
        $live = $this->displayLine($this->buffer);

        if ($this->completed === '') {
            return $live;
        }

        if ($live === '') {
            return $this->completed;
        }

        return $this->completed."\n".$live;
    }

    private function drainCompletedLines(): void
    {
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);
            $this->appendCompleted($this->displayLine($line));
        }
    }

    private function appendCompleted(string $line): void
    {
        if ($line === '') {
            return;
        }

        $this->completed = $this->completed === '' ? $line : $this->completed."\n".$line;
    }

    private function displayLine(string $chunk): string
    {
        if ($chunk === '') {
            return '';
        }

        if (! str_contains($chunk, "\r")) {
            return trim($chunk);
        }

        $parts = explode("\r", $chunk);

        for ($index = count($parts) - 1; $index >= 0; $index--) {
            $part = trim($parts[$index]);

            if ($part !== '') {
                return $part;
            }
        }

        return '';
    }

    private function maybePersist(): void
    {
        $now = microtime(true) * 1000;

        if ($this->lastPersistedAt > 0.0 && ($now - $this->lastPersistedAt) < $this->throttleMilliseconds) {
            return;
        }

        $this->persistNow();
    }

    private function persistNow(): void
    {
        $this->lastPersistedAt = microtime(true) * 1000;
        ($this->persist)($this->output());
    }
}
