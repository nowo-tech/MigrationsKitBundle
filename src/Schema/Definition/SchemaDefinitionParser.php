<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema\Definition;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Parses a declarative schema definition (array) into a Doctrine DBAL Schema.
 *
 * Array format:
 * <code>
 * [
 *   'tables' => [
 *     'table_name' => [
 *       'columns' => [
 *         'id' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true],
 *         'email' => ['type' => 'string', 'length' => 180, 'notnull' => true],
 *         'created_at' => ['type' => 'datetime_immutable', 'notnull' => false],
 *       ],
 *       'primary_key' => ['id'],
 *       'indexes' => [
 *         'idx_email' => ['columns' => ['email'], 'unique' => true],
 *       ],
 *       'options' => ['charset' => 'utf8mb4'],
 *     ],
 *   ],
 * ]
 * </code>
 *
 * Column options: type (required), length, precision, scale, notnull, default, autoincrement, comment.
 * Supported types: string, integer, smallint, bigint, boolean, decimal, float, text, datetime, datetime_immutable, date, time, json, blob, guid, etc. (DBAL type names).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaDefinitionParser
{
    /**
     * Build a Doctrine Schema from the declarative array definition.
     */
    public function parse(array $definition): Schema
    {
        $schema = new Schema();
        $tables = $definition[MDK::TABLES] ?? [];

        foreach ($tables as $tableName => $tableDef) {
            if (!\is_array($tableDef) || empty($tableDef[MDK::COLUMNS])) {
                continue;
            }
            $table = $this->parseTable((string) $tableName, $tableDef);
            try {
                $schema->createTable($table);
            } catch (\Throwable $e) {
                // DBAL 4.x: createTable() may expect (string $name) or Table::__construct rejects Table; inject via reflection
                $msg = $e->getMessage();
                if ((str_contains($msg, 'Table::__construct') && str_contains($msg, 'must be of type string'))
                    || (str_contains($msg, 'createTable') && str_contains($msg, 'must be of type string'))) {
                    $this->schemaAddTable($schema, $table);
                } else {
                    throw $e;
                }
            }
        }

        return $schema;
    }

    /**
     * Add a table to the schema (DBAL 4 compatibility when createTable(Table) is not supported).
     */
    private function schemaAddTable(Schema $schema, Table $table): void
    {
        $ref = new \ReflectionClass($schema);
        $propName = $ref->hasProperty('_tables') ? '_tables' : MDK::TABLES;
        $prop = $ref->getProperty($propName);
        if (!$prop->isPublic()) {
            $prop->setAccessible(true);
        }
        $tables = $prop->getValue($schema);
        $tables[$table->getName()] = $table;
        $prop->setValue($schema, $tables);
    }

    private function parseTable(string $tableName, array $tableDef): Table
    {
        $columns = $tableDef[MDK::COLUMNS] ?? [];
        $primaryKey = $tableDef[MDK::PRIMARY_KEY] ?? null;
        $indexes = $tableDef[MDK::INDEXES] ?? [];
        $options = $tableDef['options'] ?? [];

        $table = new Table($tableName);

        foreach ($columns as $columnName => $columnDef) {
            if (!\is_array($columnDef) || empty($columnDef['type'])) {
                continue;
            }
            $type = $columnDef['type'];
            $colOptions = $this->normalizeColumnOptions($columnDef);
            $table->addColumn($columnName, $type, $colOptions);
        }

        if (\is_array($primaryKey) && $primaryKey !== []) {
            $table->setPrimaryKey($primaryKey);
        }

        foreach ($indexes as $indexName => $indexDef) {
            $indexCols = $indexDef['columns'] ?? $indexDef;
            if (!\is_array($indexCols)) {
                $indexCols = [$indexCols];
            }
            $unique = $indexDef['unique'] ?? false;
            if ($unique) {
                $table->addUniqueIndex($indexCols, (string) $indexName);
            } else {
                $table->addIndex($indexCols, (string) $indexName);
            }
        }

        foreach ($options as $optName => $optValue) {
            if (method_exists($table, 'addOption')) {
                $table->addOption((string) $optName, $optValue);
            }
        }

        return $table;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeColumnOptions(array $columnDef): array
    {
        $opts = [];
        $map = [
            'length' => 'length',
            'precision' => 'precision',
            'scale' => 'scale',
            'notnull' => 'notnull',
            'default' => 'default',
            'autoincrement' => 'autoincrement',
            'comment' => 'comment',
            'unsigned' => 'unsigned',
            'fixed' => 'fixed',
        ];
        foreach ($map as $key => $optionKey) {
            if (array_key_exists($key, $columnDef)) {
                $opts[$optionKey] = $columnDef[$key];
            }
        }

        return $opts;
    }
}
