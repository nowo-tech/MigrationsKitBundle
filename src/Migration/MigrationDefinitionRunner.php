<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

/**
 * Runs migration steps from an array definition.
 *
 * You pass an "addSql" callable (e.g. from AbstractMigration) and an array of steps.
 * Each step is executed only if the corresponding schema check fails (e.g. create table only if not exists).
 *
 * Array format:
 * - tables: array of table definitions. Each key is table name, value is array with:
 *   - 'create_sql': string (SQL to create the table) — executed only if table does not exist
 * - columns: array of column definitions. Each item: ['table' => string, 'column' => string, 'add_sql' => string]
 *   — add_sql executed only if column does not exist
 * - indexes: array of index definitions. Each item: ['table' => string, 'index_name' => string, 'add_sql' => string]
 *   — add_sql executed only if index does not exist
 * - rename_columns: array of renames. Each item: ['table' => string, 'old_name' => string, 'new_name' => string, 'rename_sql' => string]
 *   — rename_sql executed only if column old_name exists
 * - modify_columns: array of column changes. Each item: ['table' => string, 'column' => string, 'modify_sql' => string]
 *   — modify_sql executed only if column exists
 * - drop_indexes: array of index drops. Each item: ['table' => string, 'index_name' => string, 'drop_sql' => string]
 *   — drop_sql executed only if index exists
 * - drop_columns: array of column drops. Each item: ['table' => string, 'column' => string, 'drop_sql' => string]
 *   — drop_sql executed only if column exists
 * - data: array of data steps (insert/update). Each item:
 *   - insert: ['table' => string, 'row' => array col=>value, 'only_if_not_exists' => optional array col=>value]
 *   - update: ['table' => string, 'set' => array col=>value, 'where' => array col=>value, 'only_if_exists' => optional bool]
 *   addSql is called with (string $sql, array $params = []) for data steps.
 *
 * Steps run in order: tables, then columns, then data. You can create a table, add columns,
 * and insert/update data on that table in the same run().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MigrationDefinitionRunner
{
    public function __construct(
        private readonly SchemaCheckerInterface $schemaChecker,
    ) {
    }

    /**
     * Run definition: ensure tables and columns from array; call addSql only when needed.
     *
     * Accepts (array $definition, callable $addSql) or (callable $addSql, array $definition) for backwards compatibility.
     *
     * @param array<string, mixed>|callable $definitionOrAddSql Keys: 'tables' => [...], 'columns' => [...] OR addSql callable
     * @param callable|array<string, mixed> $addSqlOrDefinition addSql callable OR definition array
     */
    public function run(array|callable $definitionOrAddSql, array|callable $addSqlOrDefinition): void
    {
        $definition = $this->resolveDefinition($definitionOrAddSql, $addSqlOrDefinition);
        $addSql = $this->resolveAddSql($definitionOrAddSql, $addSqlOrDefinition, $definition);

        $tables = $definition[MDK::TABLES] ?? [];
        foreach ($tables as $tableName => $tableDef) {
            if (!\is_array($tableDef) || empty($tableDef['create_sql'])) {
                continue;
            }
            if (!$this->schemaChecker->tableExists($tableName)) {
                $addSql($tableDef['create_sql']);
            }
        }

        $columns = $definition[MDK::COLUMNS] ?? [];
        foreach ($columns as $col) {
            if (!\is_array($col) || empty($col['table']) || empty($col['column']) || empty($col['add_sql'])) {
                continue;
            }
            if (!$this->schemaChecker->columnExists($col['table'], $col['column'])) {
                $addSql($col['add_sql']);
            }
        }

        $indexes = $definition[MDK::INDEXES] ?? [];
        foreach ($indexes as $idx) {
            if (!\is_array($idx) || empty($idx['table']) || empty($idx['index_name']) || empty($idx['add_sql'])) {
                continue;
            }
            if (!$this->schemaChecker->indexExists($idx['table'], $idx['index_name'])) {
                $addSql($idx['add_sql']);
            }
        }

        $renameColumns = $definition[MDK::RENAME_COLUMNS] ?? [];
        foreach ($renameColumns as $rc) {
            if (!\is_array($rc) || empty($rc['table']) || empty($rc['old_name']) || empty($rc['rename_sql'])) {
                continue;
            }
            if ($this->schemaChecker->columnExists($rc['table'], $rc['old_name'])) {
                $addSql($rc['rename_sql']);
            }
        }

        $modifyColumns = $definition[MDK::MODIFY_COLUMNS] ?? [];
        foreach ($modifyColumns as $mc) {
            if (!\is_array($mc) || empty($mc['table']) || empty($mc['column']) || empty($mc['modify_sql'])) {
                continue;
            }
            if ($this->schemaChecker->columnExists($mc['table'], $mc['column'])) {
                $addSql($mc['modify_sql']);
            }
        }

        $dropIndexes = $definition[MDK::DROP_INDEXES] ?? [];
        foreach ($dropIndexes as $di) {
            if (!\is_array($di) || empty($di['table']) || empty($di['index_name']) || empty($di['drop_sql'])) {
                continue;
            }
            if ($this->schemaChecker->indexExists($di['table'], $di['index_name'])) {
                $addSql($di['drop_sql']);
            }
        }

        $dropColumns = $definition[MDK::DROP_COLUMNS] ?? [];
        foreach ($dropColumns as $dc) {
            if (!\is_array($dc) || empty($dc['table']) || empty($dc['column']) || empty($dc['drop_sql'])) {
                continue;
            }
            if ($this->schemaChecker->columnExists($dc['table'], $dc['column'])) {
                $addSql($dc['drop_sql']);
            }
        }

        $dataSteps = $definition[MDK::DATA] ?? [];
        foreach ($dataSteps as $step) {
            if (!\is_array($step)) {
                continue;
            }
            if (isset($step[MDK::INSERT])) {
                $this->runInsertStep($step[MDK::INSERT], $addSql);
            } elseif (isset($step[MDK::UPDATE])) {
                $this->runUpdateStep($step[MDK::UPDATE], $addSql);
            }
        }
    }

    /**
     * @param array<string, mixed> $insertDef table, row, optional only_if_not_exists
     * @param callable             $addSql   (string $sql, array $params = []): void
     */
    private function runInsertStep(array $insertDef, callable $addSql): void
    {
        $table = $insertDef['table'] ?? null;
        $row = $insertDef['row'] ?? null;
        if ($table === null || $table === '' || !\is_array($row) || $row === []) {
            return;
        }
        $onlyIfNotExists = $insertDef['only_if_not_exists'] ?? null;
        if (\is_array($onlyIfNotExists) && $onlyIfNotExists !== [] && $this->schemaChecker->rowExists($table, $onlyIfNotExists)) {
            return;
        }
        $platform = $this->schemaChecker->getConnection()->getDatabasePlatform();
        $quotedTable = $platform->quoteIdentifier($table);
        $cols = array_keys($row);
        $quotedCols = array_map(fn (string $c) => $platform->quoteIdentifier($c), $cols);
        $placeholders = array_fill(0, \count($row), '?');
        $sql = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $quotedCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $params = array_values($row);
        $addSql($sql, $params);
    }

    /**
     * @param array<string, mixed> $updateDef table, set, where, optional only_if_exists
     * @param callable             $addSql   (string $sql, array $params = []): void
     */
    private function runUpdateStep(array $updateDef, callable $addSql): void
    {
        $table = $updateDef['table'] ?? null;
        $set = $updateDef['set'] ?? null;
        $where = $updateDef['where'] ?? null;
        if ($table === null || $table === '' || !\is_array($set) || $set === [] || !\is_array($where) || $where === []) {
            return;
        }
        $onlyIfExists = $updateDef['only_if_exists'] ?? false;
        if ($onlyIfExists && !$this->schemaChecker->rowExists($table, $where)) {
            return;
        }
        $platform = $this->schemaChecker->getConnection()->getDatabasePlatform();
        $quotedTable = $platform->quoteIdentifier($table);
        $setParts = [];
        $params = [];
        foreach ($set as $col => $value) {
            $setParts[] = $platform->quoteIdentifier((string) $col) . ' = ?';
            $params[] = $value;
        }
        $whereParts = [];
        foreach ($where as $col => $value) {
            $whereParts[] = $platform->quoteIdentifier((string) $col) . ' = ?';
            $params[] = $value;
        }
        $sql = 'UPDATE ' . $quotedTable . ' SET ' . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $whereParts);
        $addSql($sql, $params);
    }

    /**
     * @param array<string, mixed> $arr
     * @param list<string>        $keys
     */
    private function hasAnyKey(array $arr, array $keys): bool
    {
        foreach ($keys as $key) {
            if (isset($arr[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDefinition(array|callable $definitionOrAddSql, array|callable $addSqlOrDefinition): array
    {
        $definitionKeys = MDK::allTopLevel();
        $firstIsDefinition = \is_array($definitionOrAddSql) && $this->hasAnyKey($definitionOrAddSql, $definitionKeys);
        $secondIsDefinition = \is_array($addSqlOrDefinition) && $this->hasAnyKey($addSqlOrDefinition, $definitionKeys);
        if ($firstIsDefinition && !$secondIsDefinition) {
            return $definitionOrAddSql;
        }
        if ($secondIsDefinition && !$firstIsDefinition) {
            return $addSqlOrDefinition;
        }
        if ($firstIsDefinition && $secondIsDefinition) {
            return $definitionOrAddSql;
        }
        throw new \InvalidArgumentException(sprintf(
            'MigrationDefinitionRunner::run() expects (array $definition, callable $addSql) or (callable $addSql, array $definition), got (%s, %s).',
            get_debug_type($definitionOrAddSql),
            get_debug_type($addSqlOrDefinition)
        ));
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function resolveAddSql(array|callable $definitionOrAddSql, array|callable $addSqlOrDefinition, array $definition): callable
    {
        foreach ([$definitionOrAddSql, $addSqlOrDefinition] as $candidate) {
            if ($candidate === $definition) {
                continue;
            }
            if (\is_callable($candidate)) {
                return $candidate;
            }
            // [$this, 'addSql'] is an array; addSql is protected so it cannot be invoked from this class. Migrations must pass a closure instead, e.g. fn (string $sql): void => $this->addSql($sql).
            if (\is_array($candidate) && \count($candidate) === 2 && ($candidate[1] ?? null) === 'addSql' && \is_object($candidate[0] ?? null)) {
                throw new \InvalidArgumentException(
                    'MigrationDefinitionRunner::run() received [$this, \'addSql\'] but addSql() is protected. Pass a closure instead, e.g. run($definition, fn (string $sql): void => $this->addSql($sql));'
                );
            }
        }
        throw new \InvalidArgumentException(sprintf(
            'MigrationDefinitionRunner::run() expects one argument to be a callable (e.g. [$this, \'addSql\']), got (%s, %s).',
            get_debug_type($definitionOrAddSql),
            get_debug_type($addSqlOrDefinition)
        ));
    }

    /**
     * Ensure a table exists; run createSql only if it does not.
     */
    public function ensureTable(string $tableName, string $createSql, callable $addSql): void
    {
        if ($this->schemaChecker->tableExists($tableName)) {
            return;
        }
        $addSql($createSql);
    }

    /**
     * Ensure a column exists; run addSql only if it does not.
     */
    public function ensureColumn(string $tableName, string $columnName, string $addColumnSql, callable $addSql): void
    {
        if ($this->schemaChecker->columnExists($tableName, $columnName)) {
            return;
        }
        $addSql($addColumnSql);
    }

    /**
     * Ensure an index exists; run addSql only if it does not.
     */
    public function ensureIndex(string $tableName, string $indexName, string $addIndexSql, callable $addSql): void
    {
        if ($this->schemaChecker->indexExists($tableName, $indexName)) {
            return;
        }
        $addSql($addIndexSql);
    }

    /**
     * Modify a column; run modifySql only if the column exists.
     */
    public function modifyColumn(string $tableName, string $columnName, string $modifySql, callable $addSql): void
    {
        if (!$this->schemaChecker->columnExists($tableName, $columnName)) {
            return;
        }
        $addSql($modifySql);
    }

    /**
     * Drop a column; run dropSql only if the column exists.
     */
    public function dropColumn(string $tableName, string $columnName, string $dropSql, callable $addSql): void
    {
        if (!$this->schemaChecker->columnExists($tableName, $columnName)) {
            return;
        }
        $addSql($dropSql);
    }

    /**
     * Drop an index; run dropSql only if the index exists.
     */
    public function dropIndex(string $tableName, string $indexName, string $dropSql, callable $addSql): void
    {
        if (!$this->schemaChecker->indexExists($tableName, $indexName)) {
            return;
        }
        $addSql($dropSql);
    }

    /**
     * Ensure a foreign key exists; run addFkSql only if the FK does not exist.
     */
    public function ensureForeignKey(string $tableName, string $fkName, string $addFkSql, callable $addSql): void
    {
        if ($this->schemaChecker->foreignKeyExists($tableName, $fkName)) {
            return;
        }
        $addSql($addFkSql);
    }
}
