<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;

use function array_filter;
use function array_values;
use function in_array;

/**
 * Result of {@see ExactKeyChecker::check()}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactKeyReport
{
    /**
     * @param string $platform short platform name (mysql, mariadb, or the DBAL platform class)
     * @param bool $supported false where the question does not arise (PostgreSQL, SQLite): the check is skipped
     * @param string $expectedCollation platform-correct collation suggested in the ALTER statements
     * @param bool $degraded the server offers no NO PAD collation; utf8mb4_bin is accepted as the MySQL 5.7 fallback
     * @param list<ExactKeyFinding> $findings one per declared column, in declaration order
     * @param int $declaringBundles how many services declared columns
     * @param list<ExactKeyTableFinding> $tableFindings one per table that holds a declared, active, not-ignored
     *                                                  column: its own default collation, which is what a text column
     *                                                  added later without an explicit COLLATE inherits (2.9)
     */
    public function __construct(
        public string $platform,
        public bool $supported,
        public string $expectedCollation = '',
        public bool $degraded = false,
        public array $findings = [],
        public int $declaringBundles = 0,
        public array $tableFindings = [],
    ) {
    }

    /**
     * @return list<ExactKeyFinding>
     */
    public function withStatus(ExactKeyStatus ...$statuses): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (ExactKeyFinding $f): bool => in_array($f->status, $statuses, true),
        ));
    }

    /**
     * @return list<ExactKeyFinding>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->findings, static fn (ExactKeyFinding $f): bool => $f->status->isFailure()));
    }

    /**
     * @return list<ExactKeyTableFinding>
     */
    public function tableFailures(): array
    {
        return array_values(array_filter($this->tableFindings, static fn (ExactKeyTableFinding $f): bool => $f->status->isFailure()));
    }

    /**
     * @return list<ExactKeyTableFinding>
     */
    public function tablesWithStatus(ExactKeyStatus ...$statuses): array
    {
        return array_values(array_filter(
            $this->tableFindings,
            static fn (ExactKeyTableFinding $f): bool => in_array($f->status, $statuses, true),
        ));
    }

    public function isOk(): bool
    {
        return $this->failures() === [] && $this->tableFailures() === [];
    }
}
