<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema;

/**
 * Reusable definitions for standard audit columns and indexes.
 *
 * Use in declarative schema (SchemaSync) by merging into your table definition,
 * or in MigrationDefinitionRunner::run() via auditColumnSteps() to add these
 * columns to an existing table (only if they do not exist).
 *
 * Standard fields:
 * - created_at, updated_at (datetime_immutable, nullable by default)
 * - created_by, updated_by (integer, nullable; with indexes)
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class StandardColumns
{
    /**
     * Timestamp columns for declarative definition (SchemaSync).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function timestampColumns(bool $nullable = true): array
    {
        $notnull = !$nullable;

        return [
            'created_at' => ['type' => 'datetime_immutable', 'notnull' => $notnull],
            'updated_at' => ['type' => 'datetime_immutable', 'notnull' => $notnull],
        ];
    }

    /**
     * User reference columns for declarative definition (SchemaSync).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function userRefColumns(bool $nullable = true): array
    {
        $notnull = !$nullable;

        return [
            'created_by' => ['type' => 'integer', 'notnull' => $notnull],
            'updated_by' => ['type' => 'integer', 'notnull' => $notnull],
        ];
    }

    /**
     * Full audit columns (timestamps + user refs) for declarative definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function auditColumns(bool $nullable = true): array
    {
        return array_merge(
            self::timestampColumns($nullable),
            self::userRefColumns($nullable)
        );
    }

    /**
     * Indexes for created_by / updated_by (for declarative definition).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function auditIndexes(): array
    {
        return [
            'idx_created_by' => ['columns' => ['created_by']],
            'idx_updated_by' => ['columns' => ['updated_by']],
        ];
    }

    /**
     * Column steps to add only timestamp columns (created_at, updated_at) to an existing table.
     *
     * @return list<array{table: string, column: string, add_sql: string}>
     */
    public static function timestampColumnSteps(string $table, bool $isSqlite): array
    {
        $t = self::quoteId($table, $isSqlite);
        if ($isSqlite) {
            return [
                ['table' => $table, 'column' => 'created_at', 'add_sql' => sprintf('ALTER TABLE %s ADD COLUMN created_at DATETIME DEFAULT NULL', $t)],
                ['table' => $table, 'column' => 'updated_at', 'add_sql' => sprintf('ALTER TABLE %s ADD COLUMN updated_at DATETIME DEFAULT NULL', $t)],
            ];
        }
        return [
            ['table' => $table, 'column' => 'created_at', 'add_sql' => sprintf('ALTER TABLE %s ADD created_at DATETIME DEFAULT NULL', $t)],
            ['table' => $table, 'column' => 'updated_at', 'add_sql' => sprintf('ALTER TABLE %s ADD updated_at DATETIME DEFAULT NULL', $t)],
        ];
    }

    /**
     * Column steps to add only user ref columns (created_by, updated_by) to an existing table.
     *
     * @return list<array{table: string, column: string, add_sql: string}>
     */
    public static function userRefColumnSteps(string $table, bool $isSqlite): array
    {
        $t = self::quoteId($table, $isSqlite);
        if ($isSqlite) {
            return [
                ['table' => $table, 'column' => 'created_by', 'add_sql' => sprintf('ALTER TABLE %s ADD COLUMN created_by INTEGER DEFAULT NULL', $t)],
                ['table' => $table, 'column' => 'updated_by', 'add_sql' => sprintf('ALTER TABLE %s ADD COLUMN updated_by INTEGER DEFAULT NULL', $t)],
            ];
        }
        return [
            ['table' => $table, 'column' => 'created_by', 'add_sql' => sprintf('ALTER TABLE %s ADD created_by INT DEFAULT NULL', $t)],
            ['table' => $table, 'column' => 'updated_by', 'add_sql' => sprintf('ALTER TABLE %s ADD updated_by INT DEFAULT NULL', $t)],
        ];
    }

    /**
     * Column steps to add full audit columns to an existing table via MigrationDefinitionRunner.
     * Use in run() under 'columns'; each column is added only if it does not exist.
     *
     * @return list<array{table: string, column: string, add_sql: string}>
     */
    public static function auditColumnSteps(string $table, bool $isSqlite): array
    {
        return array_merge(
            self::timestampColumnSteps($table, $isSqlite),
            self::userRefColumnSteps($table, $isSqlite)
        );
    }

    /**
     * Index steps to add standard audit indexes. Use with ensureIndex() in a loop.
     *
     * @return list<array{table: string, index: string, add_sql: string}>
     */
    public static function auditIndexSteps(string $table, bool $isSqlite): array
    {
        $t = self::quoteId($table, $isSqlite);
        $createdBy = self::quoteId('created_by', $isSqlite);
        $updatedBy = self::quoteId('updated_by', $isSqlite);

        return [
            ['table' => $table, 'index' => 'idx_created_by', 'add_sql' => sprintf('CREATE INDEX idx_created_by ON %s (%s)', $t, $createdBy)],
            ['table' => $table, 'index' => 'idx_updated_by', 'add_sql' => sprintf('CREATE INDEX idx_updated_by ON %s (%s)', $t, $updatedBy)],
        ];
    }

    private static function quoteId(string $id, bool $isSqlite): string
    {
        if ($isSqlite) {
            return '"' . str_replace('"', '""', $id) . '"';
        }
        return '`' . str_replace('`', '``', $id) . '`';
    }
}
