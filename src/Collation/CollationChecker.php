<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use InvalidArgumentException;

use function count;
use function explode;
use function fnmatch;
use function is_array;
use function is_string;
use function strtolower;

/**
 * Compares the collation of a database, its tables and its string columns with one expected collation.
 *
 * A mixed collation breaks joins and comparisons on string columns (MySQL error 1267 "Illegal mix of collations"):
 * a table created with the server default (utf8mb4_0900_ai_ci on MySQL 8+) next to utf8mb4_unicode_ci tables is the
 * usual cause. MySQL / MariaDB: table and column collations (and, optionally, the database default). PostgreSQL: the
 * database collation (optional) and columns with an explicit COLLATE (tables have none). The character set is compared
 * at database level only (on MySQL a collation implies it). Other platforms are reported as not supported.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CollationChecker
{
    /**
     * @param string|null $expectedCollation null = the connection's Doctrine default_table_options collation
     * @param string|null $expectedCharset null = the default_table_options charset, else (MySQL) the collation prefix
     * @param list<string> $ignoreTables table names or fnmatch() patterns allowed to differ (never required to)
     * @param list<string> $ignoreColumns "table.column" names or fnmatch() patterns allowed to differ
     */
    public function __construct(
        private ?string $expectedCollation = null,
        private ?string $expectedCharset = null,
        private bool $checkColumns = true,
        private bool $checkDatabase = false,
        private array $ignoreTables = [],
        private array $ignoreColumns = [],
    ) {
    }

    /**
     * @param string|null $expectedCollation overrides the configured / Doctrine default collation for this run
     * @param list<string> $extraIgnoredTables added to ignore_tables for this run
     *
     * @throws InvalidArgumentException when the platform is supported and no expected collation is configured nor in
     *                                  default_table_options
     */
    public function check(Connection $connection, ?string $expectedCollation = null, array $extraIgnoredTables = []): CollationReport
    {
        $platform     = $connection->getDatabasePlatform();
        $tableOptions = $connection->getParams()['defaultTableOptions'] ?? [];

        $collation = $this->nonEmpty($expectedCollation)
            ?? $this->nonEmpty($this->expectedCollation)
            ?? $this->nonEmpty($tableOptions['collation'] ?? null)
            ?? $this->nonEmpty($tableOptions['collate'] ?? null);
        $charset      = $this->nonEmpty($this->expectedCharset) ?? $this->nonEmpty($tableOptions['charset'] ?? null);
        $ignoreTables = [...$this->ignoreTables, ...$extraIgnoredTables];
        $mysql        = $platform instanceof AbstractMySQLPlatform;

        if (!$mysql && !$platform instanceof PostgreSQLPlatform) {
            return new CollationReport($platform::class, false, $collation ?? '', $charset);
        }
        if ($collation === null) {
            throw new InvalidArgumentException('No expected collation: set nowo_migrations_kit.collation_check.expected_collation, pass --expected, or set doctrine.dbal default_table_options.collation.');
        }

        if ($mysql) {
            return $this->checkMySql($connection, $platform instanceof MariaDBPlatform ? 'mariadb' : 'mysql', $collation, $charset ?? explode('_', $collation, 2)[0], $ignoreTables);
        }

        return $this->checkPostgreSql($connection, $collation, $charset, $ignoreTables);
    }

    /**
     * @param list<string> $ignoreTables
     */
    private function checkMySql(Connection $connection, string $name, string $collation, string $charset, array $ignoreTables): CollationReport
    {
        $mismatches = [];

        if ($this->checkDatabase) {
            $row = $connection->fetchAssociative('SELECT DEFAULT_CHARACTER_SET_NAME AS charset_name, DEFAULT_COLLATION_NAME AS collation_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()');
            if (is_array($row)) {
                $this->compare($mismatches, CollationMismatch::SCOPE_DATABASE, (string) $connection->getDatabase(), (string) ($row['collation_name'] ?? ''), $collation, (string) ($row['charset_name'] ?? ''), $charset, false);
            }
        }

        $tables = $connection->fetchAllAssociative("SELECT TABLE_NAME AS table_name, TABLE_COLLATION AS collation_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
        foreach ($tables as $row) {
            $table = (string) $row['table_name'];
            $this->compare($mismatches, CollationMismatch::SCOPE_TABLE, $table, (string) ($row['collation_name'] ?? ''), $collation, null, null, $this->matches($table, $ignoreTables));
        }

        $columns = 0;
        if ($this->checkColumns) {
            $rows = $connection->fetchAllAssociative("SELECT c.TABLE_NAME AS table_name, c.COLUMN_NAME AS column_name, c.COLLATION_NAME AS collation_name FROM information_schema.COLUMNS c JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE' WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLLATION_NAME IS NOT NULL ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION");
            foreach ($rows as $row) {
                ++$columns;
                $this->compareColumn($mismatches, $row, $collation, $ignoreTables);
            }
        }

        return new CollationReport($name, true, $collation, $charset, $mismatches, count($tables), $columns);
    }

    /**
     * @param list<string> $ignoreTables
     */
    private function checkPostgreSql(Connection $connection, string $collation, ?string $charset, array $ignoreTables): CollationReport
    {
        $mismatches = [];

        if ($this->checkDatabase) {
            $row = $connection->fetchAssociative('SELECT datcollate AS collation_name, pg_encoding_to_char(encoding) AS charset_name FROM pg_database WHERE datname = current_database()');
            if (is_array($row)) {
                $this->compare($mismatches, CollationMismatch::SCOPE_DATABASE, (string) $connection->getDatabase(), (string) ($row['collation_name'] ?? ''), $collation, (string) ($row['charset_name'] ?? ''), $charset, false);
            }
        }

        $tables = (int) $connection->fetchOne("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ANY (current_schemas(false)) AND table_type = 'BASE TABLE'");

        $columns = 0;
        if ($this->checkColumns) {
            // Only columns with an explicit COLLATE carry one; the others follow the database collation.
            $rows = $connection->fetchAllAssociative("SELECT c.table_name, c.column_name, c.collation_name FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name AND t.table_type = 'BASE TABLE' WHERE c.table_schema = ANY (current_schemas(false)) AND c.collation_name IS NOT NULL ORDER BY c.table_name, c.ordinal_position");
            foreach ($rows as $row) {
                ++$columns;
                $this->compareColumn($mismatches, $row, $collation, $ignoreTables);
            }
        }

        return new CollationReport('postgresql', true, $collation, $charset, $mismatches, $tables, $columns);
    }

    /**
     * @param list<CollationMismatch> $mismatches
     * @param array<string, mixed> $row table_name, column_name, collation_name
     * @param list<string> $ignoreTables
     */
    private function compareColumn(array &$mismatches, array $row, string $collation, array $ignoreTables): void
    {
        $table  = (string) $row['table_name'];
        $object = $table . '.' . $row['column_name'];
        $this->compare(
            $mismatches,
            CollationMismatch::SCOPE_COLUMN,
            $object,
            (string) ($row['collation_name'] ?? ''),
            $collation,
            null,
            null,
            $this->matches($table, $ignoreTables) || $this->matches($object, $this->ignoreColumns),
        );
    }

    /**
     * @param list<CollationMismatch> $mismatches
     * @param CollationMismatch::SCOPE_* $scope
     */
    private function compare(array &$mismatches, string $scope, string $object, string $actualCollation, string $collation, ?string $actualCharset, ?string $charset, bool $ignored): void
    {
        if (strtolower($actualCollation) !== strtolower($collation)) {
            $mismatches[] = new CollationMismatch($scope, $object, 'collation', $actualCollation === '' ? '(none)' : $actualCollation, $collation, $ignored);
        }
        if ($charset !== null && $actualCharset !== null && strtolower($actualCharset) !== strtolower($charset)) {
            $mismatches[] = new CollationMismatch($scope, $object, 'charset', $actualCharset, $charset, $ignored);
        }
    }

    /**
     * @param list<string> $patterns
     */
    private function matches(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $name || fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    private function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
