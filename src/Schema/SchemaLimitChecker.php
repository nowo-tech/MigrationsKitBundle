<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema;

/**
 * Checks a declarative schema definition against platform limits (MySQL/InnoDB).
 *
 * Emits warnings when limits may be exceeded: max columns per table, max row size,
 * max index key length, max indexes per table. Use before sync() to avoid runtime errors.
 * For SQLite and PostgreSQL, check() returns no warnings (bundle is compatible with all three).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaLimitChecker
{
    /** InnoDB practical limit (MySQL 8). */
    private const MYSQL_MAX_COLUMNS_PER_TABLE = 1017;

    /** MySQL row size limit (bytes). */
    private const MYSQL_MAX_ROW_SIZE = 65535;

    /** InnoDB index key prefix (DYNAMIC row format, bytes). */
    private const MYSQL_MAX_INDEX_KEY_LENGTH = 3072;

    /** Max secondary indexes per table. */
    private const MYSQL_MAX_INDEXES_PER_TABLE = 64;

    /** Max columns in a single index. */
    private const MYSQL_MAX_INDEX_COLUMNS = 16;

    /** Approximate bytes per character utf8mb4. */
    private const BYTES_PER_CHAR_UTF8MB4 = 4;

    /**
     * Check definition against platform limits and return list of warning messages.
     *
     * @param array<string, mixed> $definition Declarative schema (tables with columns, indexes)
     * @param string              $platform  Platform name (e.g. mysql, sqlite)
     *
     * @return array<int, string> Warning messages (empty if no issues)
     */
    public function check(array $definition, string $platform = 'mysql'): array
    {
        $platform = strtolower($platform);
        if ($platform !== 'mysql' && !str_contains($platform, 'maria')) {
            return [];
        }

        $warnings = [];
        $tables = $definition['tables'] ?? [];

        foreach ($tables as $tableName => $tableDef) {
            if (!\is_array($tableDef)) {
                continue;
            }
            $columns = $tableDef['columns'] ?? [];
            $indexes = $tableDef['indexes'] ?? [];
            $columnCount = \count($columns);

            if ($columnCount > self::MYSQL_MAX_COLUMNS_PER_TABLE) {
                $warnings[] = sprintf(
                    '[%s] Table has %d columns; MySQL/InnoDB limit is %d.',
                    $tableName,
                    $columnCount,
                    self::MYSQL_MAX_COLUMNS_PER_TABLE
                );
            }

            $rowSize = $this->estimateRowSize($columns);
            if ($rowSize > self::MYSQL_MAX_ROW_SIZE) {
                $warnings[] = sprintf(
                    '[%s] Estimated row size %d bytes exceeds MySQL limit of %d.',
                    $tableName,
                    $rowSize,
                    self::MYSQL_MAX_ROW_SIZE
                );
            }

            $indexCount = \count($indexes);
            if ($indexCount > self::MYSQL_MAX_INDEXES_PER_TABLE) {
                $warnings[] = sprintf(
                    '[%s] Table has %d indexes; MySQL limit is %d per table.',
                    $tableName,
                    $indexCount,
                    self::MYSQL_MAX_INDEXES_PER_TABLE
                );
            }

            foreach ($indexes as $indexName => $indexDef) {
                $indexCols = $indexDef['columns'] ?? $indexDef;
                if (!\is_array($indexCols)) {
                    $indexCols = [$indexCols];
                }
                if (\count($indexCols) > self::MYSQL_MAX_INDEX_COLUMNS) {
                    $warnings[] = sprintf(
                        '[%s] Index "%s" has %d columns; MySQL limit is %d per index.',
                        $tableName,
                        $indexName,
                        \count($indexCols),
                        self::MYSQL_MAX_INDEX_COLUMNS
                    );
                }
                $indexLength = $this->estimateIndexLength($indexCols, $columns);
                if ($indexLength > self::MYSQL_MAX_INDEX_KEY_LENGTH) {
                    $warnings[] = sprintf(
                        '[%s] Index "%s" estimated key length %d bytes may exceed InnoDB limit (%d) for utf8mb4.',
                        $tableName,
                        $indexName,
                        $indexLength,
                        self::MYSQL_MAX_INDEX_KEY_LENGTH
                    );
                }
            }
        }

        return $warnings;
    }

    /**
     * Check and trigger E_USER_WARNING for each warning (e.g. in migrations).
     */
    public function warnIfOverLimits(array $definition, string $platform = 'mysql'): void
    {
        $warnings = $this->check($definition, $platform);
        foreach ($warnings as $msg) {
            trigger_error('[MigrationsKitBundle SchemaLimitChecker] ' . $msg, E_USER_WARNING);
        }
    }

    /**
     * Rough row size estimate (fixed-length types + varchar length * 4 for utf8mb4).
     */
    private function estimateRowSize(array $columns): int
    {
        $size = 0;
        foreach ($columns as $colDef) {
            if (!\is_array($colDef)) {
                continue;
            }
            $type = strtolower((string) ($colDef['type'] ?? ''));
            $length = (int) ($colDef['length'] ?? 0);
            $precision = (int) ($colDef['precision'] ?? 0);
            $scale = (int) ($colDef['scale'] ?? 0);

            if ($type === 'string' || $type === 'varchar') {
                $size += $length > 0 ? $length * self::BYTES_PER_CHAR_UTF8MB4 : 255 * self::BYTES_PER_CHAR_UTF8MB4;
            } elseif ($type === 'text' || $type === 'json') {
                $size += 12; // stored off-row
            } elseif ($type === 'blob') {
                $size += 12;
            } elseif ($type === 'integer' || $type === 'smallint') {
                $size += 4;
            } elseif ($type === 'bigint') {
                $size += 8;
            } elseif ($type === 'decimal') {
                $size += $precision > 0 ? (int) ceil($precision / 2) + 2 : 8;
            } elseif ($type === 'float' || $type === 'double') {
                $size += 8;
            } elseif (str_contains($type, 'datetime') || $type === 'date' || $type === 'time') {
                $size += 8;
            } elseif ($type === 'boolean') {
                $size += 1;
            } else {
                $size += 16; // fallback
            }
        }

        return $size;
    }

    /**
     * Estimate index key length (sum of indexed column lengths; varchar * 4 for utf8mb4).
     */
    private function estimateIndexLength(array $indexColumnNames, array $tableColumns): int
    {
        $length = 0;
        foreach ($indexColumnNames as $colName) {
            $colDef = $tableColumns[$colName] ?? [];
            if (!\is_array($colDef)) {
                $length += 255 * self::BYTES_PER_CHAR_UTF8MB4;
                continue;
            }
            $type = strtolower((string) ($colDef['type'] ?? ''));
            $colLength = (int) ($colDef['length'] ?? 0);
            if ($type === 'string' || $type === 'varchar') {
                $length += ($colLength > 0 ? $colLength : 255) * self::BYTES_PER_CHAR_UTF8MB4;
            } elseif ($type === 'integer' || $type === 'smallint') {
                $length += 4;
            } elseif ($type === 'bigint') {
                $length += 8;
            } else {
                $length += 255 * self::BYTES_PER_CHAR_UTF8MB4;
            }
        }

        return $length;
    }
}
