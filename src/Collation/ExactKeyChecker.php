<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumnsInterface;
use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;
use Throwable;

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
 * Since 2.9 the check also reads the OWN DEFAULT of every table that holds a declared column
 * (information_schema.TABLES.TABLE_COLLATION). Correct columns on a table whose default folds case and accents is not
 * a safe state, only a deferred one: the first text column anyone adds to that table later without an explicit
 * COLLATE inherits the default, and then either a join against a binary key fails with MySQL error 1267 or - worse -
 * a lookup on the new column starts folding values REQ-SEC-006 requires to stay distinct. Reading only the columns
 * was a blind spot with a measured cost: on a real host this check reported "68 ok, 0 fallback" over a database whose
 * table default was wrong, and only the generic uniformity gate caught it.
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
     * @param string|null $uniformCollation the collation this host uses for ordinary text
     *                                      (nowo_migrations_kit.collation_check.expected_collation); null = the
     *                                      connection's doctrine default_table_options.collation, and null again when
     *                                      the host declared none anywhere. It is the expected value of a table's own
     *                                      default and the collation named in the remedy - never the binary NO PAD
     *                                      collation of the key columns themselves. {@see UniformCollation}
     * @param bool $checkTableDefaults false skips the table-default check entirely (no TABLE_COLLATION comparison, no
     *                                 folding probe, nothing to raise). Mandatory where the risk is - the host-side
     *                                 command, which runs against the live database - and opt-in for a bundle's own
     *                                 gate, whose test schema holds no real identities. {@see ExactKeyColumnsTestCase}
     */
    public function __construct(
        private iterable $providers = [],
        private array $ignoreColumns = [],
        private ?string $uniformCollation = null,
        private bool $checkTableDefaults = true,
    ) {
    }

    /**
     * @throws Throwable when the live schema or the server's collation catalogue cannot be read: the check reports a
     *                   misconfiguration (exit 2) rather than a pass it cannot stand behind
     */
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

        $tableFindings = $this->checkTableDefaults ? $this->inspectTables($connection, $declared, $schema, $support) : [];

        return new ExactKeyReport($name, true, $support->expected, $support->degraded, $findings, $bundles, $tableFindings);
    }

    /**
     * The own default of every table that holds at least one declared, active, not-ignored column.
     *
     * Tables absent from the schema are left out: that is already a MISSING column failure, and saying it twice adds
     * nothing. A table whose only declared columns the host moved elsewhere is left out too - the gate has no claim
     * on a table this declaration no longer describes.
     *
     * @param list<ExactKeyColumn> $declared
     * @param array{tables: array<string, string>, columns: array<string, array<string, mixed>>} $schema
     *
     * @throws Throwable when the server cannot say whether a table default folds: never silently a pass
     *
     * @return list<ExactKeyTableFinding>
     */
    private function inspectTables(Connection $connection, array $declared, array $schema, ExactCollationSupport $support): array
    {
        $tables = [];
        foreach ($declared as $column) {
            $table = strtolower($column->table);
            if ($column->active && $this->ignoreReason($column) === null && isset($schema['tables'][$table])) {
                $tables[$table] = true;
            }
        }

        if ($tables === []) {
            return [];
        }

        $expected = UniformCollation::collation($connection, null, $this->uniformCollation);

        $findings = [];
        foreach (array_keys($tables) as $table) {
            $findings[] = $this->inspectTable($connection, $table, $schema['tables'][$table], $expected, $support);
        }

        return $findings;
    }

    /**
     * @param string $actual the table's own default collation, "" when the catalogue had no value for it
     * @param string|null $expected the host's uniform collation, null when it declared none anywhere
     *
     * @throws Throwable
     */
    private function inspectTable(Connection $connection, string $table, string $actual, ?string $expected, ExactCollationSupport $support): ExactKeyTableFinding
    {
        if ($actual === '') {
            // The table is there and its default is not readable. Reporting that as "ok" is the one direction a gate
            // must never fail in, so it is a failure with the reason named.
            return new ExactKeyTableFinding($table, ExactKeyStatus::FAIL, '', 'information_schema.TABLES.TABLE_COLLATION is empty for this table: its own default cannot be read, so no text column added to it later can be vouched for. Grant SELECT on information_schema to the connection used by the check.');
        }

        if ($expected !== null) {
            if (strtolower($actual) === strtolower($expected)) {
                return new ExactKeyTableFinding($table, ExactKeyStatus::OK, $actual, 'table default, as configured for this host');
            }

            return new ExactKeyTableFinding(
                $table,
                ExactKeyStatus::FAIL,
                $actual,
                sprintf('the table default is not this host\'s uniform collation "%s": the first text column added here later without an explicit COLLATE inherits "%s" instead, and a join against a declared binary key then fails with MySQL error 1267 - or the new column silently folds values REQ-SEC-006 keeps distinct', $expected, $actual),
                TableCollationReader::alter($table, (string) UniformCollation::charset($connection, null, $expected), $expected),
            );
        }

        if (!CollationFoldingProbe::folds($connection, $actual, $support)) {
            return new ExactKeyTableFinding($table, ExactKeyStatus::OK, $actual, 'table default, case- and accent-sensitive (asked of the server)');
        }

        // No uniform collation is configured anywhere, so there is no value to compare with - but the damage is real
        // and naming a collation the host never declared would be a guess. Fail, and say which decision is missing.
        return new ExactKeyTableFinding(
            $table,
            ExactKeyStatus::FAIL,
            $actual,
            sprintf('the table default "%s" folds case and accents (asked of the server): the first text column added here later without an explicit COLLATE inherits it and starts folding values REQ-SEC-006 keeps distinct. Set doctrine.dbal.default_table_options.collation (or nowo_migrations_kit.collation_check.expected_collation) to the collation this host uses for ordinary text, then run ALTER TABLE `%s` DEFAULT CHARACTER SET <that charset> COLLATE <that collation>; - never CONVERT TO, which would rewrite the declared key columns out of their binary collation', $actual, $table),
        );
    }

    /**
     * @param array{tables: array<string, string>, columns: array<string, array<string, mixed>>} $schema
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
     * Existence AND own default collation of the tables, in one read: the same query shape {@see CollationChecker}
     * uses, through {@see TableCollationReader}, so there is one place where TABLE_COLLATION is selected.
     *
     * @param list<string> $tables
     *
     * @return array{tables: array<string, string>, columns: array<string, array<string, mixed>>} a table maps to its
     *                                                                                            own default collation, "" when the catalogue has no value for it
     */
    private function readSchema(Connection $connection, array $tables): array
    {
        if ($tables === []) {
            return ['tables' => [], 'columns' => []];
        }

        $found = TableCollationReader::mysql($connection, $tables);

        $rows = $connection->fetchAllAssociative(
            'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, COLLATION_NAME AS collation_name, COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) IN (?)',
            [$tables],
            [ArrayParameterType::STRING],
        );

        $schema = ['tables' => [], 'columns' => []];
        foreach ($found as $table) {
            $schema['tables'][strtolower($table['table_name'])] = $table['collation_name'];
        }
        foreach ($rows as $row) {
            $schema['columns'][strtolower($row['table_name'] . '.' . $row['column_name'])] = $row;
        }

        return $schema;
    }
}
