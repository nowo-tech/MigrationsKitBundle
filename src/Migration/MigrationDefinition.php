<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

/**
 * Typed migration definition for use with MigrationDefinitionRunner.
 *
 * Combine tables, columns, indexes, rename_columns, modify_columns, drop_indexes,
 * drop_columns and data in one object and call run() in your migration.
 *
 * @phpstan-type TableDef array{create_sql: string}
 * @phpstan-type ColumnStep array{table: string, column: string, add_sql: string}
 * @phpstan-type IndexStep array{table: string, index_name: string, add_sql: string}
 * @phpstan-type RenameColumnStep array{table: string, old_name: string, new_name: string, rename_sql: string}
 * @phpstan-type ModifyColumnStep array{table: string, column: string, modify_sql: string}
 * @phpstan-type DropIndexStep array{table: string, index_name: string, drop_sql: string}
 * @phpstan-type DropColumnStep array{table: string, column: string, drop_sql: string}
 * @phpstan-type DataStep array{insert?: array{table: string, row: array<string, mixed>, only_if_not_exists?: array<string, mixed>}, update?: array{table: string, set: array<string, mixed>, where: array<string, mixed>, only_if_exists?: bool}}
 *
 * @phpstan-type DefinitionArray array{
 *   tables?: array<string, TableDef>,
 *   columns?: list<ColumnStep>,
 *   indexes?: list<IndexStep>,
 *   rename_columns?: list<RenameColumnStep>,
 *   modify_columns?: list<ModifyColumnStep>,
 *   drop_indexes?: list<DropIndexStep>,
 *   drop_columns?: list<DropColumnStep>,
 *   data?: list<DataStep>
 * }
 */
final readonly class MigrationDefinition
{
    /**
     * @param array<string, array{create_sql: string}> $tables
     * @param list<array{table: string, column: string, add_sql: string}> $columns
     * @param list<array{table: string, index_name: string, add_sql: string}> $indexes
     * @param list<array{table: string, old_name: string, new_name: string, rename_sql: string}> $renameColumns
     * @param list<array{table: string, column: string, modify_sql: string}> $modifyColumns
     * @param list<array{table: string, index_name: string, drop_sql: string}> $dropIndexes
     * @param list<array{table: string, column: string, drop_sql: string}> $dropColumns
     * @param list<array{insert?: array{table: string, row: array<string, mixed>, only_if_not_exists?: array<string, mixed>}, update?: array{table: string, set: array<string, mixed>, where: array<string, mixed>, only_if_exists?: bool}}> $data
     */
    public function __construct(
        public array $tables = [],
        public array $columns = [],
        public array $indexes = [],
        public array $renameColumns = [],
        public array $modifyColumns = [],
        public array $dropIndexes = [],
        public array $dropColumns = [],
        public array $data = [],
    ) {
    }

    /**
     * Build from the same array shape accepted by MigrationDefinitionRunner::run().
     *
     * @param DefinitionArray $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self(
            tables: $definition[MDK::TABLES] ?? [],
            columns: $definition[MDK::COLUMNS] ?? [],
            indexes: $definition[MDK::INDEXES] ?? [],
            renameColumns: $definition[MDK::RENAME_COLUMNS] ?? [],
            modifyColumns: $definition[MDK::MODIFY_COLUMNS] ?? [],
            dropIndexes: $definition[MDK::DROP_INDEXES] ?? [],
            dropColumns: $definition[MDK::DROP_COLUMNS] ?? [],
            data: $definition[MDK::DATA] ?? [],
        );
    }

    /**
     * Run this definition with the given runner and addSql callable.
     *
     * @param callable(string $sql, array<int, mixed> $params =): void $addSql
     */
    public function run(MigrationDefinitionRunner $runner, callable $addSql): void
    {
        $runner->run($this->toArray(), $addSql);
    }

    /**
     * Convert to the array format expected by MigrationDefinitionRunner::run().
     *
     * @return DefinitionArray
     */
    public function toArray(): array
    {
        $definition = [];
        if ($this->tables !== []) {
            $definition[MDK::TABLES] = $this->tables;
        }
        if ($this->columns !== []) {
            $definition[MDK::COLUMNS] = $this->columns;
        }
        if ($this->indexes !== []) {
            $definition[MDK::INDEXES] = $this->indexes;
        }
        if ($this->renameColumns !== []) {
            $definition[MDK::RENAME_COLUMNS] = $this->renameColumns;
        }
        if ($this->modifyColumns !== []) {
            $definition[MDK::MODIFY_COLUMNS] = $this->modifyColumns;
        }
        if ($this->dropIndexes !== []) {
            $definition[MDK::DROP_INDEXES] = $this->dropIndexes;
        }
        if ($this->dropColumns !== []) {
            $definition[MDK::DROP_COLUMNS] = $this->dropColumns;
        }
        if ($this->data !== []) {
            $definition[MDK::DATA] = $this->data;
        }

        return $definition;
    }
}
