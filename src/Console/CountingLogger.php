<?php

declare(strict_types=1);

namespace Rezepte\Console;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Forwards messages and counts warnings for the build summary.
 */
final class CountingLogger extends AbstractLogger
{
    private int $warnings = 0;

    public function __construct(private readonly LoggerInterface $inner)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($level === LogLevel::WARNING) {
            $this->warnings++;
        }
        $this->inner->log($level, $message, $context);
    }

    public function warnings(): int
    {
        return $this->warnings;
    }
}
