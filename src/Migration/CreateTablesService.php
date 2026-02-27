<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

use function count;
use function in_array;
use function is_array;
use function is_object;

/**
 * Applies a definition to create tables and add missing columns using the migrations API.
 *
 * Order of operations (apply execution order; phases not implemented are TODO):
 *   1. DONE: Drop FKs that reference tables in DROP_TABLES; drop FKs by name (DROP_FOREIGN_KEYS); drop indexes (DROP_INDEXES).
 *      FKs dropped in Phase 1b are tracked so Phase 2a does not emit a duplicate DROP FOREIGN KEY when the same table
 *      also has DROP_COLUMNS for a column referenced by that FK.
 *   2. DONE: Drop columns (DROP_COLUMNS); drop primary keys (DROP_PRIMARY_KEYS); drop tables (DROP_TABLES).
 *   3. DONE: Create or edit columns and tables (CREATE TABLE; Phase 3a rename columns RENAME; Phase 3b modify column type/options; ALTER TABLE ADD COLUMN).
 *   4. DONE: Create indexes and unique (Phase 4a, INDEXES); create foreign keys (Phase 4b, ADD CONSTRAINT when table and columns exist; FK not already present).
 *
 * Uses the Schema you pass (typically from introspectSchema()) for checks:
 * - If the table does not exist: emits CREATE TABLE (with all defined columns and primary key).
 * - If the table exists: for each column in the definition that is not yet in the table,
 *   emits ALTER TABLE ADD COLUMN (via Schema Comparator).
 *   Phase 3a: for column defs with RENAME (name => old, rename => new), emits RENAME COLUMN (or platform equivalent).
 *   Phase 3b: for column defs that exist but differ in type/options, emits ALTER COLUMN (via Comparator).
 *   Phase 4a: for INDEXES in table def, emits CREATE INDEX / UNIQUE when table and columns exist and index name not present.
 *
 * Usage in a migration:
 *   $schema = $this->connection->createSchemaManager()->introspectSchema();
 *   foreach ($service->apply($schema, $definition) as $sql) {
 *       $this->addSql($sql);
 *   }
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CreateTablesService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaDefinitionParser $parser,
    ) {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns SQL statements to create missing tables and add missing columns.
     *
     * Execution order: phase 1 (drop FKs/indexes), phase 2 (drop columns, drop PKs, drop tables),
     * phase 3 (create/alter tables and columns, add/change PK), phase 4 (create indexes and FKs).
     *
     * For each table in the definition:
     * - Table does not exist → CREATE TABLE with all columns and primary key.
     * - Table exists → ALTER TABLE ADD COLUMN for each column in the definition that is not in the table.
     * Column definitions with "drop" => true are skipped (no add).
     *
     * @param array<string, mixed> $definition Must contain MDK::TABLES (table name => table def with COLUMNS and optionally PRIMARY_KEY)
     *
     * @return list<string>
     */
    public function apply(Schema $schema, array $definition): array
    {
        $sqls          = [];
        $platform      = $this->connection->getDatabasePlatform();
        $schemaManager = $this->connection->createSchemaManager();
        $comparator    = $schemaManager->createComparator();

        // Phase 1: drop FKs that reference any table we are about to drop (so DROP TABLE can succeed).
        $dropTables = $this->normalizeDropTablesList($definition[MDK::DROP_TABLES] ?? null);
        if ($dropTables !== []) {
            foreach ($this->collectForeignKeysReferencingTables($schema, $dropTables) as [$localTable, $fk]) {
                $dropFkSql = $this->getDropForeignKeySQL($localTable, $fk, $platform);
                if ($dropFkSql !== null) {
                    $sqls[] = $dropFkSql;
                }
            }
        }

        $tablesDef = $definition[MDK::TABLES] ?? [];
        // Duplicate DROP FOREIGN KEY fix: FKs already dropped in Phase 1b are stored in $alreadyDroppedFkByTable,
        // and in Phase 2a duplicate DROP FOREIGN KEY statements are filtered out via isDropForeignKeySqlForTableAndFk().
        /** @var array<string, array<string, true>> tableName => fkName => true (FKs already emitted in Phase 1b to avoid duplicate in Phase 2a) */
        $alreadyDroppedFkByTable = [];
        if (is_array($tablesDef)) {
            // Phase 1b: drop FKs by name (DROP_FOREIGN_KEYS per table).
            foreach ($tablesDef as $tableName => $tableDef) {
                if (!is_array($tableDef)) {
                    continue;
                }
                $dropFks = $tableDef[MDK::DROP_FOREIGN_KEYS] ?? null;
                if (!is_array($dropFks)) {
                    continue;
                }
                $localTable = $this->getTableByShortName($schema, (string) $tableName);
                if ($localTable === null) {
                    continue;
                }
                $tableNameStr = (string) $tableName;
                foreach ($dropFks as $fkName) {
                    $fkName = $this->normalizeIdentifier($fkName);
                    if ($fkName === '') {
                        continue;
                    }
                    if (!$localTable->hasForeignKey($fkName)) {
                        continue;
                    }
                    $fk        = $localTable->getForeignKey($fkName);
                    $dropFkSql = $this->getDropForeignKeySQL($localTable, $fk, $platform);
                    if ($dropFkSql !== null) {
                        $sqls[]                                          = $dropFkSql;
                        $alreadyDroppedFkByTable[$tableNameStr][$fkName] = true;
                    }
                }
            }
            // Phase 1c: drop indexes (DROP_INDEXES per table).
            foreach ($tablesDef as $tableName => $tableDef) {
                if (!is_array($tableDef)) {
                    continue;
                }
                $dropIndexes = $tableDef[MDK::DROP_INDEXES] ?? null;
                if (!is_array($dropIndexes)) {
                    continue;
                }
                $localTable = $this->getTableByShortName($schema, (string) $tableName);
                if ($localTable === null) {
                    continue;
                }
                foreach ($dropIndexes as $indexName) {
                    $indexName = $this->normalizeIdentifier($indexName);
                    if ($indexName === '') {
                        continue;
                    }
                    if (!$localTable->hasIndex($indexName)) {
                        continue;
                    }
                    $index        = $localTable->getIndex($indexName);
                    $dropIndexSql = $this->getDropIndexSQL($localTable, $index, $platform);
                    if ($dropIndexSql !== null) {
                        $sqls[] = $dropIndexSql;
                    }
                }
            }
        }

        // Phase 2a: drop columns (DROP_COLUMNS per table).
        if (is_array($tablesDef)) {
            foreach ($tablesDef as $tableName => $tableDef) {
                if (!is_array($tableDef)) {
                    continue;
                }
                $dropColumns = $tableDef[MDK::DROP_COLUMNS] ?? null;
                if (!is_array($dropColumns)) {
                    continue;
                }
                $localTable = $this->getTableByShortName($schema, (string) $tableName);
                if ($localTable === null) {
                    continue;
                }
                $toDrop = [];
                foreach ($dropColumns as $colName) {
                    $colName = $this->normalizeIdentifier($colName);
                    if ($colName !== '' && $this->tableHasColumn($localTable, $colName)) {
                        $toDrop[] = $colName;
                    }
                }
                if ($toDrop !== []) {
                    $tableNameStr   = (string) $tableName;
                    $alterSqls      = $this->dropColumnsViaComparator($schema, $tableNameStr, $toDrop, $comparator, $platform, $schemaManager);
                    $alreadyDropped = $alreadyDroppedFkByTable[$tableNameStr] ?? [];
                    // Skip duplicate DROP FOREIGN KEY statements (already emitted in Phase 1b).
                    foreach ($alterSqls as $sql) {
                        if ($this->isDropForeignKeySqlForTableAndFk($sql, $tableNameStr, $alreadyDropped)) {
                            continue;
                        }
                        $sqls[] = $sql;
                    }
                }
            }
            // Phase 2b: drop primary key (DROP_PRIMARY_KEYS per table).
            foreach ($tablesDef as $tableName => $tableDef) {
                if (!is_array($tableDef)) {
                    continue;
                }
                $dropPk = $tableDef[MDK::DROP_PRIMARY_KEYS] ?? null;
                if ($dropPk === null) {
                    continue;
                }
                if ($dropPk === false) {
                    continue;
                }
                if (is_array($dropPk) && $dropPk === []) {
                    // empty array means "drop the primary key"
                } elseif (!is_array($dropPk) && !$dropPk) {
                    continue;
                }
                $localTable = $this->getTableByShortName($schema, (string) $tableName);
                if ($localTable === null) {
                    continue;
                }
                $pk = $localTable->getPrimaryKey();
                if ($pk === null) {
                    continue;
                }
                $dropPkSql = $this->getDropPrimaryKeySQL($localTable, $platform);
                if ($dropPkSql !== null) {
                    foreach ((array) $dropPkSql as $sql) {
                        $sqls[] = $sql;
                    }
                }
            }
        }

        // Phase 2c: drop tables (only if they exist).
        foreach ($dropTables as $tableName) {
            if ($this->schemaHasTable($schema, $tableName)) {
                $table = $this->getTableByShortName($schema, $tableName);
                if ($table !== null) {
                    foreach ($this->getDropTableSQLList($table, $platform) as $sql) {
                        $sqls[] = $sql;
                    }
                }
            }
        }

        $tablesDef = $definition[MDK::TABLES] ?? [];
        if (!is_array($tablesDef)) {
            return $sqls;
        }

        // Phase 3: create or edit columns and tables.
        foreach ($tablesDef as $tableName => $tableDef) {
            if (!is_array($tableDef)) {
                continue;
            }
            $tableName = (string) $tableName;

            if (!$this->schemaHasTable($schema, $tableName)) {
                $columns = $tableDef[MDK::COLUMNS] ?? [];
                if (is_array($columns) && $this->tableDefHasOnlyRenameColumns($columns)) {
                    // Table does not exist and definition only has renames — nothing to create or rename.
                    continue;
                }
                $table = $this->parser->parseTable($tableName, $tableDef);
                foreach ($platform->getCreateTableSQL($table) as $sql) {
                    $sqls[] = $sql;
                }
                continue;
            }

            // Phase 3a: rename columns (name => old name, RENAME => new name).
            $renameList = $this->collectRenameColumnsForTable($schema, $tableName, $tableDef);
            foreach ($renameList as [$oldName, $newName]) {
                $renameSqls = $this->getRenameColumnSQL($schema, $tableName, $oldName, $newName, $platform, $comparator, $schemaManager);
                foreach ($renameSqls as $sql) {
                    $sqls[] = $sql;
                }
            }
            // After renames we need to re-introspect if we used comparator (schema is unchanged here; renames were applied via SQL).
            // Phase 3b: modify columns (type/options change for existing columns).
            $modifyList = $this->collectModifyColumnsForTable($schema, $tableName, $tableDef);
            foreach ($modifyList as $colDef) {
                $name = $colDef['name'] ?? '';
                if ($name === '') {
                    continue;
                }
                [, $type, $options] = $this->parser->getColumnAddArgs($colDef);
                $alterSqls          = $this->modifyColumnViaComparator($schema, $tableName, (string) $name, $type, $options, $comparator, $platform, $schemaManager);
                foreach ($alterSqls as $sql) {
                    $sqls[] = $sql;
                }
            }

            $missingColumns = $this->missingColumnsForTable($schema, $tableName, $tableDef);
            if ($missingColumns === []) {
                // Still check for add primary key when table exists without PK.
            } else {
                $alterSqls = $this->addColumnsToTableViaComparator($schema, $tableName, $missingColumns, $comparator, $platform, $schemaManager);
                foreach ($alterSqls as $sql) {
                    $sqls[] = $sql;
                }
            }

            // Add or change primary key when table exists and definition has PRIMARY_KEY (no PK → add; different PK → drop + add).
            $localTable = $this->getTableByShortName($schema, $tableName);
            if ($localTable !== null) {
                $pkDef         = $tableDef[MDK::PRIMARY_KEY] ?? null;
                $desiredPkCols = null;
                if (is_array($pkDef)) {
                    foreach ($pkDef as $item) {
                        if (!is_array($item) || !empty($item[MDK::DROP])) {
                            continue;
                        }
                        $cols = $item['columns'] ?? [];
                        if (is_array($cols) && $cols !== []) {
                            $desiredPkCols = $cols;
                            break;
                        }
                    }
                }
                if ($desiredPkCols !== null) {
                    $currentPk         = $localTable->getPrimaryKey();
                    $currentPkCols     = $currentPk !== null ? $currentPk->getColumns() : [];
                    $desiredNormalized = array_map(fn ($c) => $this->normalizeIdentifier((string) $c), $desiredPkCols);
                    $allExist          = true;
                    foreach ($desiredNormalized as $c) {
                        if (!$this->tableHasColumn($localTable, $c)) {
                            $allExist = false;
                            break;
                        }
                    }
                    $currentNormalized = array_map(fn ($c) => $this->normalizeIdentifier((string) $c), $currentPkCols);
                    $pkChanged         = $currentNormalized !== $desiredNormalized;
                    if ($allExist && ($currentPk === null || $pkChanged)) {
                        $addPkSqls = $this->addPrimaryKeyViaComparator($schema, $tableName, $desiredNormalized, $comparator, $platform, $schemaManager);
                        foreach ($addPkSqls as $sql) {
                            $sqls[] = $sql;
                        }
                    }
                }
            }

        }

        // Phase 4a: create indexes and unique constraints (only if table/columns exist and index not already present).
        // Columns being added in this apply() are considered "existing" so index/FK SQL is emitted in the same run.
        foreach ($tablesDef as $tableName => $tableDef) {
            if (!is_array($tableDef)) {
                continue;
            }
            $tableName = (string) $tableName;
            $indexes   = $tableDef[MDK::INDEXES] ?? [];
            if (!is_array($indexes)) {
                continue;
            }
            $localTable = $this->getTableByShortName($schema, $tableName);
            if ($localTable === null) {
                continue;
            }
            $missingCols       = $this->missingColumnsForTable($schema, $tableName, $tableDef);
            $columnsBeingAdded = array_flip(array_map(static fn (array $row): string => $row[0], $missingCols));
            foreach ($indexes as $idxDef) {
                if (!is_array($idxDef) || !empty($idxDef[MDK::DROP])) {
                    continue;
                }
                $columns = $idxDef['columns'] ?? null;
                if (!is_array($columns) || $columns === []) {
                    continue;
                }
                $columnNames = array_map(fn ($c) => $this->normalizeIdentifier($c), $columns);
                $columnNames = array_values(array_filter($columnNames, static fn ($c) => $c !== ''));
                if ($columnNames === []) {
                    continue;
                }
                foreach ($columnNames as $col) {
                    if (!$this->tableHasColumn($localTable, $col) && !isset($columnsBeingAdded[$col])) {
                        continue 2;
                    }
                }
                $indexName = isset($idxDef['name']) ? $this->normalizeIdentifier($idxDef['name']) : null;
                if ($indexName === null || $indexName === '') {
                    $indexName = $this->generateIndexName($tableName, $columnNames, !empty($idxDef['unique']));
                }
                if ($localTable->hasIndex($indexName)) {
                    continue;
                }
                $indexUsesNewColumns = false;
                foreach ($columnNames as $col) {
                    if (isset($columnsBeingAdded[$col])) {
                        $indexUsesNewColumns = true;
                        break;
                    }
                }
                if ($indexUsesNewColumns) {
                    $index     = new Index($indexName, $columnNames, !empty($idxDef['unique']));
                    $generated = $platform->getCreateIndexSQL($index, $this->quotedTableName($localTable, $platform));
                    foreach ((array) $generated as $sql) {
                        $sqls[] = $sql;
                    }
                } else {
                    $indexSqls = $this->addIndexViaComparator($schema, $tableName, $columnNames, $indexName, !empty($idxDef['unique']), $comparator, $platform, $schemaManager);
                    foreach ($indexSqls as $sql) {
                        $sqls[] = $sql;
                    }
                }
            }
        }

        // Phase 4b: create foreign keys (only if table/columns exist and FK not already present).
        // Local columns being added in this apply() are considered "existing" so FK SQL is emitted in the same run.
        foreach ($tablesDef as $tableName => $tableDef) {
            if (!is_array($tableDef)) {
                continue;
            }
            $tableName   = (string) $tableName;
            $foreignKeys = $tableDef[MDK::FOREIGN_KEYS] ?? [];
            if (!is_array($foreignKeys)) {
                continue;
            }
            $localTable = $this->getTableByShortName($schema, $tableName);
            if ($localTable === null) {
                continue;
            }
            $missingCols       = $this->missingColumnsForTable($schema, $tableName, $tableDef);
            $columnsBeingAdded = array_flip(array_map(static fn (array $row): string => $row[0], $missingCols));
            foreach ($foreignKeys as $fkDef) {
                if (!is_array($fkDef) || !empty($fkDef[MDK::DROP])) {
                    continue;
                }
                $localColumns     = $fkDef['columns'] ?? null;
                $foreignTableName = $fkDef['foreign_table'] ?? null;
                $foreignColumns   = $fkDef['foreign_columns'] ?? null;
                if (!is_array($localColumns) || $localColumns === [] || $foreignTableName === null || $foreignTableName === '' || !is_array($foreignColumns) || $foreignColumns === []) {
                    continue;
                }
                if (!$this->schemaHasTable($schema, (string) $foreignTableName)) {
                    continue;
                }
                $foreignTable = $this->getTableByShortName($schema, (string) $foreignTableName);
                if ($foreignTable === null) {
                    continue;
                }
                foreach ($localColumns as $col) {
                    $colNorm = $this->normalizeIdentifier((string) $col);
                    if (!$this->tableHasColumn($localTable, $colNorm) && !isset($columnsBeingAdded[$colNorm])) {
                        continue 2;
                    }
                }
                foreach ($foreignColumns as $col) {
                    if (!$this->tableHasColumn($foreignTable, (string) $col)) {
                        continue 2;
                    }
                }
                $fkName = $fkDef['name'] ?? ('fk_' . $tableName . '_' . implode('_', $localColumns));
                if ($localTable->hasForeignKey($fkName)) {
                    continue;
                }
                $options = [];
                if (isset($fkDef['onUpdate'])) {
                    $options['onUpdate'] = $fkDef['onUpdate'];
                }
                if (isset($fkDef['onDelete'])) {
                    $options['onDelete'] = $fkDef['onDelete'];
                }
                $fkUsesNewColumns = false;
                foreach ($localColumns as $col) {
                    if (isset($columnsBeingAdded[$this->normalizeIdentifier((string) $col)])) {
                        $fkUsesNewColumns = true;
                        break;
                    }
                }
                if ($fkUsesNewColumns && method_exists($platform, 'getCreateForeignKeySQL')) {
                    try {
                        $fkConstraint = new ForeignKeyConstraint(
                            $localColumns,
                            (string) $foreignTableName,
                            $foreignColumns,
                            $fkName,
                            $options,
                        );
                        $generated = $platform->getCreateForeignKeySQL($fkConstraint, $this->quotedTableName($localTable, $platform));
                        foreach ((array) $generated as $sql) {
                            $sqls[] = $sql;
                        }
                    } catch (\Doctrine\DBAL\Platforms\Exception\NotSupported) {
                        // e.g. SQLite: add FK on new columns not supported in same run
                    }
                } else {
                    $fkSqls = $this->addForeignKeyViaComparator($schema, $tableName, (string) $foreignTableName, $localColumns, $foreignColumns, $options, $fkName, $comparator, $platform, $schemaManager);
                    foreach ($fkSqls as $sql) {
                        $sqls[] = $sql;
                    }
                }
            }
        }

        return $sqls;
    }

    /**
     * Returns column definitions (as [name, type, options]) that are in $tableDef but not in the table.
     * Skips column defs with "drop" => true or without name/type.
     *
     * @param array<string, mixed> $tableDef
     *
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function missingColumnsForTable(Schema $schema, string $tableName, array $tableDef): array
    {
        $table = $this->getTableByShortName($schema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema; COLUMNS not array
        if ($table === null) {
            return [];
        }
        $columns = $tableDef[MDK::COLUMNS] ?? [];
        if (!is_array($columns)) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        $missing = [];
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            if (!empty($col[MDK::DROP])) {
                continue;
            }
            if (!empty($col[MDK::RENAME])) {
                continue;
            }
            $name = $col['name'] ?? null;
            if ($name === null || $name === '') {
                continue;
            }
            if (!isset($col['type'])) {
                continue;
            }
            if (!$this->tableHasColumn($table, (string) $name)) {
                $missing[] = $this->parser->getColumnAddArgs($col);
            }
        }

        return $missing;
    }

    /**
     * True if every column in the definition is a rename-only def (has RENAME key).
     * Used to skip CREATE TABLE when the table does not exist and the def only describes renames.
     *
     * @param list<array<string, mixed>> $columns
     */
    private function tableDefHasOnlyRenameColumns(array $columns): bool
    {
        if ($columns === []) {
            return false;
        }
        // @codeCoverageIgnoreStart - non-array column; column without RENAME
        foreach ($columns as $col) {
            if (!is_array($col)) {
                return false;
            }
            if (empty($col[MDK::RENAME])) {
                return false;
            }
        }

        return true;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Column defs with 'name' and RENAME that exist in the table → list of [oldName, newName].
     *
     * @param array<string, mixed> $tableDef
     *
     * @return list<array{0: string, 1: string}>
     */
    private function collectRenameColumnsForTable(Schema $schema, string $tableName, array $tableDef): array
    {
        $table = $this->getTableByShortName($schema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema; COLUMNS not array
        if ($table === null) {
            return [];
        }
        $columns = $tableDef[MDK::COLUMNS] ?? [];
        if (!is_array($columns)) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        $list = [];
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            if (!empty($col[MDK::DROP])) {
                continue;
            }
            $oldName = $col['name'] ?? null;
            $newName = $col[MDK::RENAME] ?? null;
            if ($oldName === null || $oldName === '' || $newName === null || $newName === '') {
                continue;
            }
            $oldName = $this->normalizeIdentifier($oldName);
            $newName = $this->normalizeIdentifier($newName);
            if ($oldName !== '' && $newName !== '' && $oldName !== $newName && $this->tableHasColumn($table, $oldName)) {
                $list[] = [$oldName, $newName];
            }
        }

        return $list;
    }

    /**
     * Generate SQL to rename a column. Uses platform getRenameColumnSQL if available, else comparator (drop + add with same spec).
     *
     * @return list<string>
     */
    private function getRenameColumnSQL(Schema $schema, string $tableName, string $oldName, string $newName, object $platform, object $comparator, object $schemaManager): array
    {
        $table = $this->getTableByShortName($schema, $tableName);
        // @codeCoverageIgnoreStart - table/column not in schema
        if ($table === null || !$this->tableHasColumn($table, $oldName)) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        $column = $table->getColumn($oldName);
        if (method_exists($platform, 'getRenameColumnSQL')) {
            $renameSql = $this->invokePlatformRenameColumnSQL($platform, $column, $newName, $tableName, $table);
            if ($renameSql !== []) {
                return $renameSql;
            }
        }
        // Fallback: comparator (clone, drop old, add new with same type/options).
        [, $typeName, $options] = $this->columnToAddArgs($column, $newName);
        $toSchema               = clone $schema;
        $t                      = $this->getTableByShortName($toSchema, $tableName);
        if ($t === null) {
            return [];
        }
        // @codeCoverageIgnoreStart - comparator fallback (rename via clone schema)
        $t->dropColumn($oldName);
        $t->addColumn($newName, $typeName === '' ? 'string' : $typeName, $options);
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($schema, $toSchema)
            : $comparator->compare($schema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Call platform getRenameColumnSQL; signature may be (Column, string, string) or (string, string, string).
     *
     * @return list<string>
     */
    private function invokePlatformRenameColumnSQL(object $platform, Column $column, string $newName, string $tableName, Table $table): array
    {
        try {
            $method = new ReflectionMethod($platform, 'getRenameColumnSQL');
            $params = $method->getParameters();
            if (count($params) >= 3) {
                $first     = $params[0]->getType();
                $firstType = $first instanceof ReflectionNamedType ? $first->getName() : '';
                // @codeCoverageIgnoreStart - platform getRenameColumnSQL(string, string, string) / DBAL 2
                if ($firstType === 'string') {
                    $str = SchemaAssetName::get($column);
                    $sql = $platform->getRenameColumnSQL($str, $newName, $tableName);
                } else {
                    $sql = $platform->getRenameColumnSQL($column, $newName, $tableName);
                }
            } else {
                $sql = $platform->getRenameColumnSQL($column, $newName, $tableName);
            }
            if (is_array($sql)) {
                return $sql;
            }

            return $sql !== null && $sql !== '' ? [(string) $sql] : [];
        } catch (Throwable) {
            // @codeCoverageIgnoreEnd
            return [];
        }
    }

    /**
     * Get [name, type, options] for addColumn from a DBAL Column (e.g. for rename: same spec, new name).
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function columnToAddArgs(Column $column, string $useName): array
    {
        $type     = $column->getType();
        $typeName = $this->getTypeName($type);
        $options  = [];
        if (method_exists($column, 'getNotnull')) {
            $options['notnull'] = $column->getNotnull();
        }
        if (method_exists($column, 'getDefault')) {
            $options['default'] = $column->getDefault();
        }
        if (method_exists($column, 'getLength') && $column->getLength() !== null) {
            $options['length'] = $column->getLength();
        }
        if (method_exists($column, 'getPrecision') && $column->getPrecision() !== null) {
            $options['precision'] = $column->getPrecision();
        }
        // @codeCoverageIgnoreStart - DBAL 2 / column without scale, autoincrement, comment
        if (method_exists($column, 'getScale') && $column->getScale() !== null) {
            $options['scale'] = $column->getScale();
        }
        if (method_exists($column, 'getAutoincrement') && $column->getAutoincrement()) {
            $options['autoincrement'] = true;
        }
        if (method_exists($column, 'getComment') && $column->getComment() !== null) {
            $options['comment'] = $column->getComment();
        }
        // @codeCoverageIgnoreEnd

        return [$useName, $typeName, $options];
    }

    /**
     * Column defs that exist in the table but differ in type/options (no RENAME, no DROP).
     *
     * @param array<string, mixed> $tableDef
     *
     * @return list<array<string, mixed>>
     */
    private function collectModifyColumnsForTable(Schema $schema, string $tableName, array $tableDef): array
    {
        $table = $this->getTableByShortName($schema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema; COLUMNS not array
        if ($table === null) {
            return [];
        }
        $columns = $tableDef[MDK::COLUMNS] ?? [];
        if (!is_array($columns)) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        $list = [];
        foreach ($columns as $col) {
            if (!is_array($col)) {
                continue;
            }
            if (!empty($col[MDK::DROP]) || !empty($col[MDK::RENAME])) {
                continue;
            }
            $name = $col['name'] ?? null;
            if ($name === null || $name === '' || !isset($col['type'])) {
                continue;
            }
            $name = (string) $name;
            if (!$this->tableHasColumn($table, $name)) {
                continue;
            }
            $current        = $table->getColumn($name);
            $desiredType    = $col['type'] ?? 'string';
            $desiredOptions = $this->parser->getColumnOptions($col);
            if ($this->columnDefDiffers($current, (string) $desiredType, $desiredOptions)) {
                $list[] = $col;
            }
        }

        return $list;
    }

    private function columnDefDiffers(Column $current, string $desiredType, array $desiredOptions): bool
    {
        $currentType     = $current->getType();
        $currentTypeName = $this->getTypeName($currentType);
        if (strtolower($currentTypeName) !== strtolower($desiredType)) {
            return true;
        }
        $opts = [
            'notnull'   => method_exists($current, 'getNotnull') ? $current->getNotnull() : true,
            'default'   => method_exists($current, 'getDefault') ? $current->getDefault() : null,
            'length'    => method_exists($current, 'getLength') ? $current->getLength() : null,
            'precision' => method_exists($current, 'getPrecision') ? $current->getPrecision() : null,
            'scale'     => method_exists($current, 'getScale') ? $current->getScale() : null,
            'comment'   => method_exists($current, 'getComment') ? $current->getComment() : null,
        ];
        foreach ($opts as $key => $currentVal) {
            $desiredVal = $desiredOptions[$key] ?? null;
            // @codeCoverageIgnoreStart - default/notnull comparison branches
            if ($key === 'default' && $currentVal !== $desiredVal) {
                if ((string) ($currentVal ?? '') !== (string) ($desiredVal ?? '')) {
                    return true;
                }
                continue;
            }
            if ($key === 'notnull') {
                if ((bool) $currentVal !== (bool) ($desiredVal ?? true)) {
                    return true;
                }
                continue;
            }
            // @codeCoverageIgnoreEnd
            if (($currentVal !== null || $desiredVal !== null) && ($currentVal ?? '') !== ($desiredVal ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return DBAL type name; compatible with DBAL 3 (Type::getName()) and DBAL 4 (method may be removed).
     */
    private function getTypeName(object $type): string
    {
        if ($type instanceof \Doctrine\DBAL\Types\Type && method_exists($type, 'getName')) {
            return $type->getName();
        }

        // @codeCoverageIgnoreStart - DBAL 4 type without getName()
        return 'string';
        // @codeCoverageIgnoreEnd
    }

    /**
     * Generate ALTER TABLE ... ALTER/MODIFY COLUMN via comparator (clone, drop column, add same name with new type/options).
     *
     * @return list<string>
     */
    private function modifyColumnViaComparator(Schema $fromSchema, string $tableName, string $columnName, string $type, array $options, object $comparator, object $platform, object $schemaManager): array
    {
        $toSchema = clone $fromSchema;
        $table    = $this->getTableByShortName($toSchema, $tableName);
        // @codeCoverageIgnoreStart - table/column not in schema
        if ($table === null || !$table->hasColumn($columnName)) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        $table->dropColumn($columnName);
        $table->addColumn($columnName, $type, $options);
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($fromSchema, $toSchema)
            : $comparator->compare($fromSchema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
    }

    /**
     * Clone schema, add the given columns to the table, compare and return ALTER SQL.
     * Compatible with DBAL 3 (SchemaDiff::toSql) and DBAL 4 (Platform::getAlterSchemaSQL or SchemaManager).
     *
     * @param list<array{0: string, 1: string, 2: array<string, mixed>}> $columnAddArgs
     *
     * @return list<string>
     */
    private function addColumnsToTableViaComparator(Schema $fromSchema, string $tableName, array $columnAddArgs, object $comparator, object $platform, object $schemaManager): array
    {
        $toSchema = clone $fromSchema;
        $table    = $this->getTableByShortName($toSchema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema
        if ($table === null) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        foreach ($columnAddArgs as [$name, $type, $options]) {
            $table->addColumn($name, $type, $options);
        }
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($fromSchema, $toSchema)
            : $comparator->compare($fromSchema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
    }

    /**
     * Clone schema, drop the given columns from the table, compare and return ALTER SQL.
     *
     * @param list<string> $columnNames
     *
     * @return list<string>
     */
    private function dropColumnsViaComparator(Schema $fromSchema, string $tableName, array $columnNames, object $comparator, object $platform, object $schemaManager): array
    {
        $toSchema = clone $fromSchema;
        $table    = $this->getTableByShortName($toSchema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema
        if ($table === null) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        // Drop FKs that reference any of the columns we are about to drop first (avoids DBAL deprecation
        // "Dropping columns referenced by constraints is deprecated")
        $columnNamesSet = array_flip(array_map([$this, 'normalizeIdentifier'], $columnNames));
        foreach ($table->getForeignKeys() as $fk) {
            $localCols = method_exists($fk, 'getLocalColumns') ? $fk->getLocalColumns() : (method_exists($fk, 'getColumns') ? $fk->getColumns() : []);
            foreach ($localCols as $col) {
                $colStr = $this->normalizeIdentifier(is_object($col) ? (string) $col : $col);
                if ($colStr !== '' && isset($columnNamesSet[$colStr])) {
                    $fkName = SchemaAssetName::get($fk);
                    if ($fkName !== '' && $table->hasForeignKey($fkName)) {
                        if (method_exists($table, 'dropForeignKeyConstraint')) {
                            $table->dropForeignKeyConstraint($fkName);
                        } elseif (method_exists($table, 'removeForeignKey')) {
                            $table->removeForeignKey($fkName);
                        }
                    }
                    break;
                }
            }
        }

        foreach ($columnNames as $name) {
            if ($table->hasColumn($name)) {
                $table->dropColumn($name);
            }
        }
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($fromSchema, $toSchema)
            : $comparator->compare($fromSchema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
    }

    private function schemaHasTable(Schema $schema, string $tableName): bool
    {
        if ($schema->hasTable($tableName)) {
            return true;
        }
        foreach ($schema->getTables() as $table) {
            if ($this->tableNameString($table) === $tableName || str_ends_with($this->tableNameString($table), '.' . $tableName)) {
                return true;
            }
        }

        return false;
    }

    private function getTableByShortName(Schema $schema, string $shortName): ?Table
    {
        if ($schema->hasTable($shortName)) {
            return $schema->getTable($shortName);
        }
        foreach ($schema->getTables() as $table) {
            $name = $this->tableNameString($table);
            if ($name === $shortName || str_ends_with($name, '.' . $shortName)) {
                return $table;
            }
        }

        return null;
    }

    private function tableNameString(Table $table): string
    {
        return SchemaAssetName::get($table);
    }

    private function tableHasColumn(Table $table, string $columnName): bool
    {
        if ($table->hasColumn($columnName)) {
            return true;
        }
        // @codeCoverageIgnoreStart - column name as object (DBAL 2)
        foreach ($table->getColumns() as $col) {
            if (SchemaAssetName::get($col) === $columnName) {
                return true;
            }
        }

        return false;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Call Table::addForeignKeyConstraint with correct parameter order for DBAL 3 vs 4.
     * DBAL 3: (foreignTable, localColumns, foreignColumns, name, options)
     * DBAL 4: (foreignTable, localColumns, foreignColumns, options, name)
     * So onDelete/onUpdate in $options are only applied when the 4th and 5th args are passed in the order the platform expects.
     *
     * Fix for "ON DELETE not emitted in ADD CONSTRAINT": reflection is used to detect the method signature
     * (tableAddForeignKeyConstraintExpectsNameAsFourthParam()) and addForeignKeyConstraintToTable() is called
     * with the correct parameter order so options are passed correctly; the generated SQL includes ON DELETE CASCADE,
     * ON DELETE SET NULL, etc.
     *
     * @param array<string, string> $options e.g. onUpdate, onDelete
     */
    private function addForeignKeyConstraintToTable(Table $table, string $foreignTableName, array $localColumns, array $foreignColumns, array $options, string $fkName): void
    {
        $nameFirst = $this->tableAddForeignKeyConstraintExpectsNameAsFourthParam($table);
        if ($nameFirst) {
            $table->addForeignKeyConstraint($foreignTableName, $localColumns, $foreignColumns, $fkName, $options);
        } else {
            $table->addForeignKeyConstraint($foreignTableName, $localColumns, $foreignColumns, $options, $fkName);
        }
    }

    /**
     * True if Table::addForeignKeyConstraint(..., 4th, 5th) expects (name, options); false if (options, name).
     *
     * @codeCoverageIgnore - reflection for DBAL 3/4 compatibility
     */
    private function tableAddForeignKeyConstraintExpectsNameAsFourthParam(Table $table): bool
    {
        try {
            $method = new ReflectionMethod($table, 'addForeignKeyConstraint');
            $params = $method->getParameters();
            if (isset($params[3])) {
                $fourth = $params[3]->getName();

                return $fourth === 'name' || $fourth === 'constraintName';
            }
        } catch (ReflectionException) {
        }

        return false;
    }

    /**
     * Clone schema, add one foreign key to the table, compare and return ALTER SQL.
     * Compatible with DBAL 3 and DBAL 4 (see schemaDiffToSql).
     *
     * @param list<string> $localColumns
     * @param list<string> $foreignColumns
     * @param array<string, string> $options e.g. onUpdate, onDelete
     *
     * @return list<string>
     */
    private function addForeignKeyViaComparator(Schema $fromSchema, string $tableName, string $foreignTableName, array $localColumns, array $foreignColumns, array $options, string $fkName, object $comparator, object $platform, object $schemaManager): array
    {
        $toSchema = clone $fromSchema;
        $table    = $this->getTableByShortName($toSchema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema
        if ($table === null) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        $this->addForeignKeyConstraintToTable($table, $foreignTableName, $localColumns, $foreignColumns, $options, $fkName);
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($fromSchema, $toSchema)
            : $comparator->compare($fromSchema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
    }

    /**
     * Generate a deterministic index name when not provided.
     * e.g. idx_kit_example_col_title or uniq_kit_example_col_guid.
     */
    private function generateIndexName(string $tableName, array $columnNames, bool $unique): string
    {
        $prefix = $unique ? 'uniq_' : 'idx_';
        $suffix = implode('_', $columnNames);

        return $prefix . $tableName . '_' . $suffix;
    }

    /**
     * Add one index (or unique constraint) to the table via comparator.
     *
     * @param list<string> $columnNames
     *
     * @return list<string>
     */
    private function addIndexViaComparator(Schema $fromSchema, string $tableName, array $columnNames, string $indexName, bool $unique, object $comparator, object $platform, object $schemaManager): array
    {
        $toSchema = clone $fromSchema;
        $table    = $this->getTableByShortName($toSchema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema
        if ($table === null) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        if ($unique) {
            $table->addUniqueIndex($columnNames, $indexName);
        } else {
            $table->addIndex($columnNames, $indexName);
        }
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($fromSchema, $toSchema)
            : $comparator->compare($fromSchema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
    }

    /**
     * Add primary key to an existing table via comparator.
     *
     * @param list<string> $columnNames
     *
     * @return list<string>
     */
    private function addPrimaryKeyViaComparator(Schema $fromSchema, string $tableName, array $columnNames, object $comparator, object $platform, object $schemaManager): array
    {
        $toSchema = clone $fromSchema;
        $table    = $this->getTableByShortName($toSchema, $tableName);
        // @codeCoverageIgnoreStart - table not in schema; dropPrimaryKey (DBAL 2)
        if ($table === null) {
            return [];
        }
        if (method_exists($table, 'dropPrimaryKey') && $table->getPrimaryKey() !== null) {
            $table->dropPrimaryKey();
        }
        // @codeCoverageIgnoreEnd
        if (method_exists($table, 'addPrimaryKeyConstraint')) {
            try {
                $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint($columnNames));
            } catch (Throwable) {
                $table->setPrimaryKey($columnNames);
            }
        } else {
            $table->setPrimaryKey($columnNames);
        }
        $diff = method_exists($comparator, 'compareSchemas')
            ? $comparator->compareSchemas($fromSchema, $toSchema)
            : $comparator->compare($fromSchema, $toSchema);

        return $this->schemaDiffToSql($diff, $platform, $schemaManager);
    }

    /**
     * Get SQL statements from SchemaDiff.
     * DBAL 3: SchemaDiff::toSql($platform).
     * DBAL 4: SchemaDiff has no toSql(); build SQL from getAlteredTables() + platform->getAlterTableSQL().
     *
     * @return list<string>
     */
    private function schemaDiffToSql(object $diff, object $platform, object $schemaManager): array
    {
        if (method_exists($diff, 'toSql')) {
            return $diff->toSql($platform);
        }
        if (method_exists($platform, 'getAlterSchemaSQL')) {
            return $platform->getAlterSchemaSQL($diff);
        }
        if (method_exists($schemaManager, 'getAlterSchemaSQL')) {
            return $schemaManager->getAlterSchemaSQL($diff);
        }
        // @codeCoverageIgnoreStart - DBAL 4 / platforms without toSql or getAlterSchemaSQL
        // DBAL 4: SchemaDiff has getAlteredTables(), no toSql(); generate SQL per TableDiff
        $sqls = [];
        if (method_exists($diff, 'getAlteredTables')) {
            foreach ($diff->getAlteredTables() as $tableDiff) {
                if (method_exists($platform, 'getAlterTableSQL')) {
                    foreach ($platform->getAlterTableSQL($tableDiff) as $sql) {
                        $sqls[] = $sql;
                    }
                }
            }
        }

        return $sqls;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Returns a normalized list of table names to drop (non-empty strings, unique).
     *
     * @param mixed $dropTables definition[MDK::DROP_TABLES]
     *
     * @return list<string>
     */
    private function normalizeDropTablesList(mixed $dropTables): array
    {
        if (!is_array($dropTables)) {
            return [];
        }
        $out = [];
        foreach ($dropTables as $name) {
            $name = $this->normalizeIdentifier($name);
            if ($name !== '' && !in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * Returns [localTable, FK] for every FK in the schema that references one of the given table names.
     * Used to drop those FKs before dropping the referenced tables.
     *
     * @param list<string> $referencedTableNames
     *
     * @return list<array{0: Table, 1: object}> [local Table, ForeignKeyConstraint]
     */
    private function collectForeignKeysReferencingTables(Schema $schema, array $referencedTableNames): array
    {
        $referencedSet = array_flip(array_map([$this, 'normalizeIdentifier'], $referencedTableNames));
        $pairs         = [];
        foreach ($schema->getTables() as $table) {
            $localName = $this->tableNameString($table);
            if (isset($referencedSet[$this->normalizeIdentifier($localName)])) {
                continue;
            }
            foreach ($table->getForeignKeys() as $fk) {
                $foreignTable = $this->foreignKeyGetForeignTableName($fk);
                if ($foreignTable !== '' && isset($referencedSet[$this->normalizeIdentifier($foreignTable)])) {
                    $pairs[] = [$table, $fk];
                }
            }
        }

        return $pairs;
    }

    private function foreignKeyGetForeignTableName(object $fk): string
    {
        if (method_exists($fk, 'getReferencedTableName')) {
            $name = $fk->getReferencedTableName();

            return $this->normalizeIdentifier($name);
        }
        // @codeCoverageIgnoreStart - DBAL 2 getForeignTableName()
        if (method_exists($fk, 'getForeignTableName')) {
            $name = $fk->getForeignTableName();

            return $this->normalizeIdentifier($name);
        }

        return '';
        // @codeCoverageIgnoreEnd
    }

    private function isSqlitePlatform(object $platform): bool
    {
        return $platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform;
    }

    private function normalizeIdentifier(mixed $name): string
    {
        // @codeCoverageIgnoreStart - edge cases: null, empty, object with toString
        if ($name === null || $name === '') {
            return '';
        }
        if (is_object($name) && method_exists($name, 'toString')) {
            $name = $name->toString();
        }
        // @codeCoverageIgnoreEnd
        $name = (string) $name;

        return trim($name, " \t\n\r\0\x0B`\"'[]");
    }

    /**
     * Returns a single DROP FOREIGN KEY SQL statement in canonical form (no backticks).
     * Using one consistent style avoids duplicate-looking statements when the same FK is
     * considered in Phase 1b and Phase 2a (the latter is then filtered out).
     */
    private function getDropForeignKeySQL(Table $localTable, object $fk, object $platform): ?string
    {
        // SQLite does not support ALTER TABLE ... DROP FOREIGN KEY; the platform throws when generating that SQL.
        if ($this->isSqlitePlatform($platform)) {
            return null;
        }
        $tableName = $this->tableNameString($localTable);
        $fkName    = $this->foreignKeyName($fk);
        if ($tableName === '' || $fkName === '') {
            return null;
        }

        // Emit canonical form without backticks so Phase 1b and filtered Phase 2a never produce two different-looking statements.
        return 'ALTER TABLE ' . $tableName . ' DROP FOREIGN KEY ' . $fkName;
    }

    /**
     * Returns true if the SQL is a DROP FOREIGN KEY for the given table and one of the given FK names
     * (used to skip duplicate DROP FOREIGN KEY when the FK was already emitted in Phase 1b).
     *
     * @param array<string, true> $alreadyDroppedFkNames map fkName => true
     */
    private function isDropForeignKeySqlForTableAndFk(string $sql, string $tableName, array $alreadyDroppedFkNames): bool
    {
        if ($alreadyDroppedFkNames === []) {
            return false;
        }
        if (stripos($sql, 'DROP FOREIGN KEY') === false) {
            return false;
        }
        if (stripos($sql, $tableName) === false) {
            return false;
        }
        foreach (array_keys($alreadyDroppedFkNames) as $fkName) {
            if (stripos($sql, $fkName) !== false) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyName(object $fk): string
    {
        $name = SchemaAssetName::get($fk);

        return $name !== '' ? $this->normalizeIdentifier($name) : '';
    }

    /**
     * @return list<string>
     */
    private function getDropTableSQLList(Table $table, object $platform): array
    {
        if (method_exists($platform, 'getDropTableSQL')) {
            // DBAL 4 requires string; DBAL 3 deprecates Table and expects quoted name. We always pass the quoted name.
            $quotedName = $this->quotedTableName($table, $platform);
            $tableArg   = $this->getDropTableSQLExpectsString($platform) ? $quotedName : $table;
            $sql        = $platform->getDropTableSQL($tableArg);

            return is_array($sql) ? $sql : [$sql];
        }

        // @codeCoverageIgnoreStart - platform has no getDropTableSQL
        return ['DROP TABLE ' . $this->quotedTableName($table, $platform)];
        // @codeCoverageIgnoreEnd
    }

    private function quotedTableName(Table $table, object $platform): string
    {
        return $this->quoteSingleIdentifier($this->tableNameString($table), $platform);
    }

    private function quoteSingleIdentifier(string $name, object $platform): string
    {
        if (method_exists($platform, 'quoteSingleIdentifier')) {
            return $platform->quoteSingleIdentifier($name);
        }
        // @codeCoverageIgnoreStart - DBAL 2 quoteIdentifier fallback
        if (method_exists($platform, 'quoteIdentifier')) {
            return $platform->quoteIdentifier($name);
        }

        return $name;
        // @codeCoverageIgnoreEnd
    }

    /** DBAL 4 uses string; DBAL 3 accepts Table (deprecated) or quoted string. @codeCoverageIgnore */
    private function getDropTableSQLExpectsString(object $platform): bool
    {
        try {
            $method = new ReflectionMethod($platform, 'getDropTableSQL');
            $params = $method->getParameters();
            if ($params !== []) {
                $type = $params[0]->getType();

                return $type instanceof ReflectionNamedType && $type->getName() === 'string';
            }
        } catch (ReflectionException) {
        }

        return false;
    }

    /**
     * @return list<string>|null
     */
    private function getDropPrimaryKeySQL(Table $table, object $platform): ?array
    {
        if ($this->isSqlitePlatform($platform)) {
            return null;
        }
        if (method_exists($platform, 'getDropPrimaryKeySQL')) {
            try {
                $ref = new ReflectionMethod($platform, 'getDropPrimaryKeySQL');
                if ($ref->isPublic()) {
                    $tableArg = $this->getDropPrimaryKeySQLExpectsString($platform) ? $this->quotedTableName($table, $platform) : $table;
                    $sql      = $platform->getDropPrimaryKeySQL($tableArg);
                    if ($sql !== null) {
                        return is_array($sql) ? $sql : [$sql];
                    }
                }
            } catch (Throwable) {
                // @codeCoverageIgnore - platform getDropPrimaryKeySQL throws or is protected
            }
        }
        $quotedName = $this->quotedTableName($table, $platform);

        return ['ALTER TABLE ' . $quotedName . ' DROP PRIMARY KEY'];
    }

    /** @codeCoverageIgnore - reflection helper for DBAL 2/3/4 compatibility */
    private function getDropPrimaryKeySQLExpectsString(object $platform): bool
    {
        try {
            $method = new ReflectionMethod($platform, 'getDropPrimaryKeySQL');
            $params = $method->getParameters();
            if ($params !== []) {
                $type = $params[0]->getType();

                return $type instanceof ReflectionNamedType && $type->getName() === 'string';
            }
        } catch (ReflectionException) {
        }

        return false;
    }

    private function getDropIndexSQL(Table $localTable, object $index, object $platform): ?string
    {
        // @codeCoverageIgnoreStart - platform has no getDropIndexSQL
        if (!method_exists($platform, 'getDropIndexSQL')) {
            $indexName = $this->indexName($index);
            if ($indexName !== '') {
                $tableName = $this->tableNameString($localTable);

                return 'DROP INDEX ' . $indexName . ' ON ' . $tableName;
            }

            return null;
        }
        // @codeCoverageIgnoreEnd
        $indexArg = $this->getDropIndexSQLExpectsString($platform) ? $this->indexName($index) : $index;
        $tableArg = $this->getDropIndexSQLExpectsTableNameString($platform) ? $this->quotedTableName($localTable, $platform) : $localTable;
        $sql      = $platform->getDropIndexSQL($indexArg, $tableArg);

        return is_array($sql) ? $sql[0] ?? null : $sql;
    }

    private function indexName(object $index): string
    {
        $name = SchemaAssetName::get($index);

        return $name !== '' ? $this->normalizeIdentifier($name) : '';
    }

    /** @codeCoverageIgnore - reflection helper for DBAL 2/3/4 compatibility */
    private function getDropIndexSQLExpectsString(object $platform): bool
    {
        try {
            $method = new ReflectionMethod($platform, 'getDropIndexSQL');
            $params = $method->getParameters();
            if ($params !== []) {
                $type = $params[0]->getType();

                return $type instanceof ReflectionNamedType && $type->getName() === 'string';
            }
        } catch (ReflectionException) {
        }

        return false;
    }

    /** @codeCoverageIgnore - reflection helper for DBAL 2/3/4 compatibility */
    private function getDropIndexSQLExpectsTableNameString(object $platform): bool
    {
        try {
            $method = new ReflectionMethod($platform, 'getDropIndexSQL');
            $params = $method->getParameters();
            if (isset($params[1])) {
                $type = $params[1]->getType();

                return $type instanceof ReflectionNamedType && $type->getName() === 'string';
            }
        } catch (ReflectionException) {
        }

        return false;
    }
}
