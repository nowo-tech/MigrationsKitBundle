<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use function array_filter;
use function array_values;

/**
 * Result of {@see CollationChecker::check()}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CollationReport
{
    /**
     * @param string $platform short platform name (mysql, mariadb, postgresql, or the DBAL platform class)
     * @param bool $supported false when the platform has no meaningful collation check (SQLite, …)
     * @param list<CollationMismatch> $mismatches every difference, ignored ones included
     * @param int $tablesChecked base tables inspected
     * @param int $columnsChecked string columns inspected
     */
    public function __construct(
        public string $platform,
        public bool $supported,
        public string $expectedCollation,
        public ?string $expectedCharset,
        public array $mismatches = [],
        public int $tablesChecked = 0,
        public int $columnsChecked = 0,
    ) {
    }

    /**
     * Differences that fail the gate.
     *
     * @return list<CollationMismatch>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->mismatches, static fn (CollationMismatch $m): bool => !$m->ignored));
    }

    /**
     * Differences allowed by ignore_tables / ignore_columns (still to convert).
     *
     * @return list<CollationMismatch>
     */
    public function ignored(): array
    {
        return array_values(array_filter($this->mismatches, static fn (CollationMismatch $m): bool => $m->ignored));
    }

    public function isOk(): bool
    {
        return $this->failures() === [];
    }
}
