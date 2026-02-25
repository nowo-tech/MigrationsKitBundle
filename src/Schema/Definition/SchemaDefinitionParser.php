<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema\Definition;

use Doctrine\DBAL\Schema\Table;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

use function array_key_exists;
use function is_array;

/**
 * Builds a DBAL Table from an array definition (columns + primary key).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaDefinitionParser
{
    /**
     * Builds a Table from a table definition array.
     *
     * @param array<string, mixed> $tableDef Must contain MDK::COLUMNS (array of column defs) and optionally MDK::PRIMARY_KEY (e.g. ['columns' => ['id']])
     */
    public function parseTable(string $tableName, array $tableDef): Table
    {
        $table   = new Table($tableName);
        $columns = $tableDef[MDK::COLUMNS] ?? [];
        if (!is_array($columns)) {
            return $table;
        }
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            if (!empty($col[MDK::DROP])) {
                continue;
            }
            $name = $col['name'] ?? null;
            if ($name === null || $name === '') {
                continue;
            }
            $type = $col['type'] ?? null;
            if ($type === null || $type === '') {
                continue;
            }
            $options = $this->columnOptions($col);
            $table->addColumn((string) $name, (string) $type, $options);
        }
        $pk = $tableDef[MDK::PRIMARY_KEY] ?? null;
        if (is_array($pk)) {
            foreach ($pk as $item) {
                if (!is_array($item) || !empty($item[MDK::DROP])) {
                    continue;
                }
                $cols = $item['columns'] ?? [];
                if (is_array($cols) && $cols !== []) {
                    $table->setPrimaryKey($cols);
                    break;
                }
            }
        }
        $indexes = $tableDef[MDK::INDEXES] ?? [];
        if (is_array($indexes)) {
            foreach ($indexes as $idx) {
                if (!is_array($idx)) {
                    continue;
                }
                $cols = $idx['columns'] ?? [];
                if (!is_array($cols)) {
                    $cols = $cols === '' ? [] : [$cols];
                }
                if ($cols === []) {
                    continue;
                }
                $name   = $idx['name'] ?? null;
                $unique = !empty($idx['unique']);
                if ($name !== null && $name !== '') {
                    if ($unique) {
                        $table->addUniqueIndex($cols, (string) $name);
                    } else {
                        $table->addIndex($cols, (string) $name);
                    }
                } else {
                    if ($unique) {
                        $table->addUniqueIndex($cols);
                    } else {
                        $table->addIndex($cols);
                    }
                }
            }
        }
        $fks = $tableDef[MDK::FOREIGN_KEYS] ?? $tableDef['foreign_keys'] ?? [];
        if (!is_array($fks)) {
            $fks = [];
        }
        foreach ($fks as $fk) {
            if (!is_array($fk)) {
                continue;
            }
            $localCols    = $fk['columns'] ?? [];
            $foreignTable = $fk['foreign_table'] ?? null;
            $foreignCols  = $fk['foreign_columns'] ?? [];
            if (!is_array($localCols) || $localCols === [] || $foreignTable === null || $foreignTable === '' || !is_array($foreignCols) || $foreignCols === []) {
                continue;
            }
            $fkName = $fk['name'] ?? null;
            if ($fkName !== null && $fkName !== '') {
                $table->addForeignKeyConstraint($foreignTable, $localCols, $foreignCols, [], (string) $fkName);
            } else {
                $table->addForeignKeyConstraint($foreignTable, $localCols, $foreignCols);
            }
        }

        return $table;
    }

    /**
     * Returns name, type and options for adding a single column (e.g. for ALTER TABLE ADD COLUMN).
     * Use this when the table already exists and you need to add missing columns.
     *
     * @param array<string, mixed> $col Column definition (name, type, length, notnull, default, etc.)
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>} [name, type, options]
     */
    public function getColumnAddArgs(array $col): array
    {
        $name    = $col['name'] ?? '';
        $type    = $col['type'] ?? 'string';
        $options = $this->columnOptions($col);

        return [(string) $name, (string) $type, $options];
    }

    /**
     * @param array<string, mixed> $col Column definition
     *
     * @return array<string, mixed> Options for Table::addColumn()
     */
    public function getColumnOptions(array $col): array
    {
        return $this->columnOptions($col);
    }

    /**
     * @param array<string, mixed> $col Column definition
     *
     * @return array<string, mixed> Options for Table::addColumn()
     */
    private function columnOptions(array $col): array
    {
        $opts = [];
        if (isset($col['notnull'])) {
            $opts['notnull'] = (bool) $col['notnull'];
        }
        if (array_key_exists('default', $col)) {
            $opts['default'] = $col['default'];
        }
        if (isset($col['length'])) {
            $opts['length'] = (int) $col['length'];
        }
        if (isset($col['precision'])) {
            $opts['precision'] = (int) $col['precision'];
        }
        if (isset($col['scale'])) {
            $opts['scale'] = (int) $col['scale'];
        }
        if (!empty($col['autoincrement'])) {
            $opts['autoincrement'] = true;
        }
        if (isset($col['comment'])) {
            $opts['comment'] = (string) $col['comment'];
        }

        return $opts;
    }
}
