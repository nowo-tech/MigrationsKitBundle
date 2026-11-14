<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see MonotonicVersionsChecker::fix()} / {@see MonotonicVersionsChecker::fixAll()} (2.8) when the
 * migrations could not be renamed. It is a {@see RuntimeException}, so code that caught that before still does.
 *
 * `$rollbackIncomplete` is false when nothing was changed (every file is as it was before the run) and true when the
 * rollback itself failed: the message then says "ROLLBACK INCOMPLETE" and lists the files that need attention.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class FixFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $rollbackIncomplete = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
