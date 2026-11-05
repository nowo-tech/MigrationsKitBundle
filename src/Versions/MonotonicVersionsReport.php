<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

/**
 * Result of {@see MonotonicVersionsChecker::check()} for one migrations directory.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MonotonicVersionsReport
{
    /**
     * @param list<string> $added versions added since the base
     * @param list<string> $violations added versions that do not sort after `baseMax`
     */
    public function __construct(
        public string $path,
        public string $base,
        public ?string $baseMax,
        public array $added,
        public array $violations,
    ) {
    }

    public function isValid(): bool
    {
        return $this->violations === [];
    }
}
