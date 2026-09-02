<?php

namespace App\Support\QueueWorkers;

final class QueueWorkerLogRedactor
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private const array Patterns = [
        ['/(?i)\b(password|passwd|secret|token|api[_-]?key|app[_-]?key|aws_secret_access_key)\s*[=:]\s*\S+/', '$1=[REDACTED]'],
        ['/\bAKIA[0-9A-Z]{16}\b/', '[REDACTED]'],
        ['/\b(?:ASIA|AIDA)[0-9A-Z]{16}\b/', '[REDACTED]'],
        ['/\bbase64:[A-Za-z0-9+\/=]{20,}/', '[REDACTED]'],
        ['/\b(?:sk_live_|sk_test_)[A-Za-z0-9]+/', '[REDACTED]'],
    ];

    public function redact(string $output): string
    {
        foreach (self::Patterns as [$pattern, $replacement]) {
            $redacted = preg_replace($pattern, $replacement, $output);

            if (is_string($redacted)) {
                $output = $redacted;
            }
        }

        return $output;
    }
}
