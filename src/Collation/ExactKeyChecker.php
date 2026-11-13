<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumnsInterface;
use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;

use function array_keys;
use function fnmatch;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Asserts that every identity / access-key column declared by an installed bundle is binary NO PAD (REQ-SEC-006).
 *
 * Positive list, not a scan. Each bundle declares its own columns through {@see ExactKeyColumnsInterface}; the check
 * reads information_schema for the live connection and fails on any declared column that is not binary NO PAD. That
 * is what catches the case no scan can see: a column nobody ever gave a COLLATE to, sitting on the server default
 * (utf8mb4_0900_ai_ci — case- and accent-insensitive AND PAD SPACE), which is strictly worse than utf8mb4_bin. A new
 * identity column is only safe once someone adds it to the list, and adding it is what forces the decision.
 *
 * The same report serves two consumers: a bundle's own CI (its test database, its own provider) and a host-side
 * aggregate run over every installed bundle's declaration at once.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactKeyChecker
{
    /**
     * @param iterable<ExactKeyColumnsInterface> $providers tagged nowo_migrations_kit.exact_key_columns
     * @param array<string, string> $ignoreColumns "table.column" (or fnmatch pattern) => why the host put it elsewhere.
     *                                             An ignored column stays in the report as "ignored by host" with its
     *                                             reason; it never disappears from the output.
     */
    public function __construct(
        private iterable $providers = [],
        private array $ignoreColumns = [],
    ) {
    }

    public function check(Connection $connection): ExactKeyReport
    {
        $platform = $connection->getDatabasePlatform();
        $support  = ExactCollationSupport::forConnection($connection);

        $declared = [];
        $bundles  = 0;
        foreach ($this->providers as $provider) {
            ++$bundles;
            foreach ($provider->exactKeyColumns() as $column) {
                $declared[] = $column;
            }
        }

        if (!$support->supported) {
            return new ExactKeyReport($platform::class, false, '', false, [], $bundles);
        }

        $name   = $platform instanceof MariaDBPlatform ? 'mariadb' : 'mysql';
        $tables = [];
        foreach ($declared as $column) {
            if ($column->active) {
                $tables[strtolower($column->table)] = true;
            }
        }

        $schema = $this->readSchema($connection, array_keys($tables));

        $findings = [];
        foreach ($declared as $column) {
            $findings[] = $this->inspect($column, $schema, $support);
        }

        return new ExactKeyReport($name, true, $support->expected, $support->degraded, $findings, $bundles);
    }

    /**
     * @param array{tables: array<string, true>, columns: array<string, array<string, mixed>>} $schema
     */
    private function inspect(ExactKeyColumn $column, array $schema, ExactCollationSupport $support): ExactKeyFinding
    {
        if (!$column->active) {
            return new ExactKeyFinding($column, ExactKeyStatus::SKIPPED, '', $column->condition ?? 'declared inactive by the bundle');
        }

        $reason = $this->ignoreReason($column);
        $row    = $schema['columns'][$column->key()] ?? null;

        if ($row === null) {
            $detail = isset($schema['tables'][strtolower($column->table)])
                ? 'table exists, column does not: renamed or dropped?'
                : 'table not in the schema: renamed, prefixed differently, or the migration has not run';

            // A column the host deliberately moved elsewhere can still be absent; say so rather than failing it.
            return $reason !== null
                ? new ExactKeyFinding($column, ExactKeyStatus::IGNORED, '', 'ignored by host: ' . $reason . '; also ' . $detail)
                : new ExactKeyFinding($column, ExactKeyStatus::MISSING, '', $detail);
        }

        $collation = is_string($row['collation_name'] ?? null) ? (string) $row['collation_name'] : '';
        $actual    = $collation === '' ? '(none)' : $collation;
        $type      = (string) ($row['column_type'] ?? '');

        if ($reason !== null) {
            return new ExactKeyFinding($column, ExactKeyStatus::IGNORED, $actual, 'ignored by host: ' . $reason);
        }

        // No collation at all means a binary or numeric type (VARBINARY, BLOB, INT, BINARY(16) uuids): byte-exact.
        if ($collation === '') {
            return new ExactKeyFinding($column, ExactKeyStatus::OK, $actual, $type . ': no collation, compared byte for byte');
        }

        if ($support->accepts($collation)) {
            return new ExactKeyFinding($column, ExactKeyStatus::OK, $actual, $type);
        }

        $alter = $this->alter($column, $row, $support->expected);

        if ($support->isFallback($collation)) {
            return new ExactKeyFinding($column, ExactKeyStatus::FALLBACK, $actual, 'binary but PAD SPACE; this server offers no NO PAD collation, so this is the best available');
        }

        return new ExactKeyFinding($column, ExactKeyStatus::FAIL, $actual, $column->purpose === '' ? $type : $column->purpose, $alter);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function alter(ExactKeyColumn $column, array $row, string $expected): string
    {
        return sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s CHARACTER SET utf8mb4 COLLATE %s%s;',
            $column->table,
            $column->column,
            (string) ($row['column_type'] ?? 'VARCHAR(255)'),
            $expected,
            ($row['is_nullable'] ?? 'YES') === 'NO' ? ' NOT NULL' : ' NULL',
        );
    }

    private function ignoreReason(ExactKeyColumn $column): ?string
    {
        $key = $column->key();
        foreach ($this->ignoreColumns as $pattern => $reason) {
            $pattern = strtolower($pattern);
            if ($pattern === $key || fnmatch($pattern, $key)) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tables
     *
     * @return array{tables: array<string, true>, columns: array<string, array<string, mixed>>}
     */
    private function readSchema(Connection $connection, array $tables): array
    {
        if ($tables === []) {
            return ['tables' => [], 'columns' => []];
        }

        $found = $connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND LOWER(TABLE_NAME) IN (?)",
            [$tables],
            [ArrayParameterType::STRING],
        );

        $rows = $connection->fetchAllAssociative(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLLATION_NAME AS collation_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) IN (?)',
            [$tables],
            [ArrayParameterType::STRING],
        );

        $schema = ['tables' => [], 'columns' => []];
        foreach ($found as $table) {
            $schema['tables'][strtolower((string) $table)] = true;
        }
        foreach ($rows as $row) {
            $schema['columns'][strtolower($row['table_name'] . '.' . $row['column_name'])] = $row;
        }

        return $schema;
    }
}
