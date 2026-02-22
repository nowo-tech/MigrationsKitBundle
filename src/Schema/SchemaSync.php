<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use RuntimeException;
use Throwable;

use function is_array;

/**
 * Syncs the database schema to match a declarative array definition.
 *
 * Compares current DB schema (introspected) with the desired schema (from array),
 * then generates and executes the necessary SQL: create/drop tables, add/drop/change columns,
 * add/drop indexes. Uses Doctrine's SchemaComparator for platform-aware diff and SQL generation.
 *
 * Requires DBAL 3.x or 4.x (introspectSchema and createComparator).
 *
 * Options (second argument to sync()):
 * - drop_tables: if true, drop tables that exist in DB but not in definition (default: false)
 * - drop_columns: if true, drop columns that exist in DB but not in definition (default: false)
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaSync
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaDefinitionParser $parser,
        private readonly SchemaChecker $schemaChecker,
    ) {
    }

    /**
     * Sync schema: apply diff between current DB and the declarative definition.
     *
     * @param callable(string): void $addSql Called for each SQL statement (e.g. [$this, 'addSql'] in a migration)
     * @param array<string, mixed> $definition Declarative schema (see SchemaDefinitionParser)
     * @param array<string, bool> $options drop_tables (bool), drop_columns (bool)
     */
    public function sync(callable $addSql, array $definition, array $options = []): void
    {
        $dropTables = $options['drop_tables'] ?? false;

        $desiredSchema = $this->parser->parse($definition);
        $currentSchema = $this->introspectSchema();
        $platform      = $this->connection->getDatabasePlatform();

        // New tables: create from definition (parser never sets schema), so platform never looks up "schema.tablename"
        $tablesDef = $definition[MDK::TABLES] ?? [];
        foreach ($tablesDef as $tableName => $tableDef) {
            if (!is_array($tableDef) || empty($tableDef[MDK::COLUMNS])) {
                continue;
            }
            $tableName = (string) $tableName;
            if ($this->schemaChecker->tableExists($tableName)) {
                continue;
            }
            $oneTableSchema = $this->parser->parse([MDK::TABLES => [$tableName => $tableDef]]);
            $tables         = $oneTableSchema->getTables();
            $parsedTable    = $tables[$tableName] ?? reset($tables);
            if ($parsedTable !== false) {
                $tableForCreate = $this->buildTableWithShortNameOnly($parsedTable, $tableName);
                try {
                    foreach ($platform->getCreateTableSQL($tableForCreate) as $sql) {
                        $addSql($sql);
                    }
                } catch (Throwable $e) {
                    if (str_contains($e->getMessage(), 'There is no table with name')) {
                        foreach ($this->buildCreateTableSQLFallback($tableForCreate, $platform) as $sql) {
                            $addSql($sql);
                        }
                    } else {
                        throw $e;
                    }
                }
            }
        }

        $comparator = $this->createComparator();
        try {
            $diff = $comparator->compareSchemas($currentSchema, $desiredSchema);
        } catch (Throwable $e) {
            if ($this->isTableDoesNotExistException($e)) {
                return;
            }
            throw $e;
        }

        $currentTables = $currentSchema->getTables();
        // Changed tables: ALTER TABLE. Skip entirely when schema is empty (only new tables); skip diffs for qualified names or non-existing tables.
        foreach ($this->getModifiedTablesFromDiff($diff) as $tableDiff) {
            if ($currentTables === []) {
                continue;
            }
            $diffName = $this->getTableDiffName($tableDiff);
            if ($diffName !== null && str_contains($diffName, '.')) {
                continue;
            }
            if (!$this->tableDiffRefersToExistingTable($tableDiff, $currentSchema)) {
                continue;
            }
            try {
                foreach ($this->getAlterTableSQL($tableDiff, $platform) as $sql) {
                    $addSql($sql);
                }
            } catch (Throwable $e) {
                if ($this->isTableDoesNotExistException($e)) {
                    continue;
                }
                throw $e;
            }
        }

        if ($dropTables) {
            $droppedTables = $this->getDroppedTablesFromDiff($diff);
            if ($droppedTables !== []) {
                $dropSqls = method_exists($platform, 'getDropTablesSQL')
                    ? $platform->getDropTablesSQL($droppedTables)
                    : array_map(static fn ($t) => $platform->getDropTableSQL(method_exists($t, 'getQuotedName') ? $t->getQuotedName($platform) : $t->getName()), $droppedTables);
                $dropSqls = is_array($dropSqls) ? $dropSqls : [$dropSqls];
                foreach ($dropSqls as $sql) {
                    $addSql($sql);
                }
            }
        }
    }

    private function isTableDoesNotExistException(Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'There is no table with name') || str_contains($msg, 'no table with name');
    }

    /**
     * Compute the diff and return SQL statements without executing (e.g. for dry-run or logging).
     *
     * @param array<string, mixed> $definition Declarative schema
     * @param array<string, bool> $options drop_tables, drop_columns
     *
     * @return array<int, string>
     */
    public function diff(array $definition, array $options = []): array
    {
        $dropTables    = $options['drop_tables'] ?? false;
        $desiredSchema = $this->parser->parse($definition);
        $currentSchema = $this->introspectSchema();
        $platform      = $this->connection->getDatabasePlatform();
        $sql           = [];

        $tablesDef = $definition[MDK::TABLES] ?? [];
        foreach ($tablesDef as $tableName => $tableDef) {
            if (!is_array($tableDef) || empty($tableDef[MDK::COLUMNS])) {
                continue;
            }
            $tableName = (string) $tableName;
            if ($this->schemaChecker->tableExists($tableName)) {
                continue;
            }
            $oneTableSchema = $this->parser->parse([MDK::TABLES => [$tableName => $tableDef]]);
            $tables         = $oneTableSchema->getTables();
            $parsedTable    = $tables[$tableName] ?? reset($tables);
            if ($parsedTable !== false) {
                $tableForCreate = $this->buildTableWithShortNameOnly($parsedTable, $tableName);
                try {
                    foreach ($platform->getCreateTableSQL($tableForCreate) as $s) {
                        $sql[] = $s;
                    }
                } catch (Throwable $e) {
                    if (str_contains($e->getMessage(), 'There is no table with name')) {
                        foreach ($this->buildCreateTableSQLFallback($tableForCreate, $platform) as $s) {
                            $sql[] = $s;
                        }
                    } else {
                        throw $e;
                    }
                }
            }
        }

        $comparator = $this->createComparator();
        try {
            $diff = $comparator->compareSchemas($currentSchema, $desiredSchema);
        } catch (Throwable $e) {
            if ($this->isTableDoesNotExistException($e)) {
                return $sql;
            }
            throw $e;
        }
        $currentTables = $currentSchema->getTables();
        foreach ($this->getModifiedTablesFromDiff($diff) as $tableDiff) {
            if ($currentTables === []) {
                continue;
            }
            $diffName = $this->getTableDiffName($tableDiff);
            if ($diffName !== null && str_contains($diffName, '.')) {
                continue;
            }
            if (!$this->tableDiffRefersToExistingTable($tableDiff, $currentSchema)) {
                continue;
            }
            try {
                foreach ($this->getAlterTableSQL($tableDiff, $platform) as $s) {
                    $sql[] = $s;
                }
            } catch (Throwable $e) {
                if ($this->isTableDoesNotExistException($e)) {
                    continue;
                }
                throw $e;
            }
        }
        if ($dropTables) {
            $droppedTables = $this->getDroppedTablesFromDiff($diff);
            if ($droppedTables !== []) {
                $dropSqls = method_exists($platform, 'getDropTablesSQL')
                    ? $platform->getDropTablesSQL($droppedTables)
                    : array_map(static fn ($t) => $platform->getDropTableSQL(method_exists($t, 'getQuotedName') ? $t->getQuotedName($platform) : $t->getName()), $droppedTables);
                $dropSqls = is_array($dropSqls) ? $dropSqls : [$dropSqls];
                foreach ($dropSqls as $s) {
                    $sql[] = $s;
                }
            }
        }

        return $sql;
    }

    /**
     * @return array<Table>
     */
    private function getCreatedTablesFromDiff(SchemaDiff $diff): array
    {
        if (method_exists($diff, 'getCreatedTables')) {
            return $diff->getCreatedTables();
        }

        return $diff->newTables ?? $diff->createdTables ?? [];
    }

    /**
     * @return array<TableDiff>
     */
    private function getModifiedTablesFromDiff(SchemaDiff $diff): array
    {
        if (method_exists($diff, 'getModifiedTables')) {
            return $diff->getModifiedTables();
        }
        if (method_exists($diff, 'getAlteredTables')) {
            return $diff->getAlteredTables();
        }

        return $diff->changedTables ?? $diff->modifiedTables ?? [];
    }

    /**
     * @return array<Table>
     */
    private function getDroppedTablesFromDiff(SchemaDiff $diff): array
    {
        if (method_exists($diff, 'getDroppedTables')) {
            return $diff->getDroppedTables();
        }

        return $diff->removedTables ?? $diff->droppedTables ?? [];
    }

    /**
     * Build a new Table with only the short name (no schema) so getCreateTableSQL never looks up "schema.tablename".
     */
    private function buildTableWithShortNameOnly(Table $source, string $shortName): Table
    {
        $t = new Table($shortName);
        foreach ($source->getColumns() as $col) {
            $opts = ['notnull' => $col->getNotnull()];
            if ($col->getDefault() !== null) {
                $opts['default'] = $col->getDefault();
            }
            if (method_exists($col, 'getLength') && $col->getLength() !== null) {
                $opts['length'] = $col->getLength();
            }
            if (method_exists($col, 'getPrecision') && $col->getPrecision() !== null) {
                $opts['precision'] = $col->getPrecision();
            }
            if (method_exists($col, 'getScale') && $col->getScale() !== null) {
                $opts['scale'] = $col->getScale();
            }
            if (method_exists($col, 'getAutoincrement') && $col->getAutoincrement()) {
                $opts['autoincrement'] = true;
            }
            if (method_exists($col, 'getComment') && $col->getComment() !== null) {
                $opts['comment'] = $col->getComment();
            }
            $type     = $col->getType();
            $typeName = method_exists($type, 'getName') ? $type->getName() : \Doctrine\DBAL\Types\Type::lookupName($type);
            $t->addColumn($col->getName(), $typeName, $opts);
        }
        $pk = $source->getPrimaryKey();
        if ($pk !== null) {
            $t->setPrimaryKey($pk->getColumns());
        }
        foreach ($source->getIndexes() as $idx) {
            if ($idx->isPrimary()) {
                continue;
            }
            if ($idx->isUnique()) {
                $t->addUniqueIndex($idx->getColumns(), $idx->getName());
            } else {
                $t->addIndex($idx->getColumns(), $idx->getName());
            }
        }

        return $t;
    }

    /**
     * Fallback when getCreateTableSQL throws TableDoesNotExist (e.g. schema.tablename lookup). Build CREATE TABLE manually.
     *
     * @return array<int, string>
     */
    private function buildCreateTableSQLFallback(Table $table, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): array
    {
        $declarations = [];
        foreach ($table->getColumns() as $col) {
            $declarations[] = $platform->getColumnDeclarationSQL(
                $col->getName(),
                method_exists($col, 'toArray') ? $col->toArray() : $this->columnToArray($col),
            );
        }
        $pk = $table->getPrimaryKey();
        if ($pk !== null) {
            $declarations[] = 'PRIMARY KEY (' . implode(', ', array_map(static fn (string $c): string => $platform->quoteIdentifier($c), $pk->getColumns())) . ')';
        }
        $sql = 'CREATE TABLE ' . $platform->quoteIdentifier($table->getName()) . ' (' . implode(', ', $declarations) . ')';

        return [$sql];
    }

    /**
     * @return array<string, mixed>
     */
    private function columnToArray(\Doctrine\DBAL\Schema\Column $col): array
    {
        $a = [
            'type'    => $col->getType(),
            'notnull' => $col->getNotnull(),
        ];
        if ($col->getDefault() !== null) {
            $a['default'] = $col->getDefault();
        }
        if (method_exists($col, 'getLength') && $col->getLength() !== null) {
            $a['length'] = $col->getLength();
        }
        if (method_exists($col, 'getPrecision') && $col->getPrecision() !== null) {
            $a['precision'] = $col->getPrecision();
        }
        if (method_exists($col, 'getScale') && $col->getScale() !== null) {
            $a['scale'] = $col->getScale();
        }
        if (method_exists($col, 'getAutoincrement') && $col->getAutoincrement()) {
            $a['autoincrement'] = true;
        }

        return $a;
    }

    private function getTableDiffName(TableDiff $tableDiff): ?string
    {
        if (property_exists($tableDiff, 'name')) {
            return $tableDiff->name;
        }
        if (method_exists($tableDiff, 'getName')) {
            return $tableDiff->getName();
        }
        if (method_exists($tableDiff, 'getOldTable')) {
            return $tableDiff->getOldTable()->getName();
        }

        return null;
    }

    /**
     * True if this TableDiff refers to a table that exists in the current schema (so we can run ALTER).
     */
    private function tableDiffRefersToExistingTable(TableDiff $tableDiff, Schema $currentSchema): bool
    {
        $name = $this->getTableDiffName($tableDiff);
        if ($name === null || $name === '') {
            return false;
        }
        if (str_contains($name, '.')) {
            return false;
        }
        if ($currentSchema->hasTable($name)) {
            return true;
        }

        return (bool) (method_exists($tableDiff, 'getFullQualifiedName') && $currentSchema->hasTable($tableDiff->getFullQualifiedName()))

        ;
    }

    /**
     * Get current database schema (introspected). Requires DBAL 3+.
     */
    private function introspectSchema(): Schema
    {
        $sm = $this->schemaChecker->getSchemaManager();
        if (method_exists($sm, 'introspectSchema')) {
            return $sm->introspectSchema();
        }
        throw new RuntimeException('SchemaSync requires Doctrine DBAL 3.x or 4.x (introspectSchema). Use SchemaChecker and MigrationDefinitionRunner for DBAL 2.x.');
    }

    private function createComparator(): Comparator
    {
        $sm = $this->schemaChecker->getSchemaManager();
        if (method_exists($sm, 'createComparator')) {
            return $sm->createComparator();
        }
        throw new RuntimeException('SchemaSync requires Doctrine DBAL 3.2+ (createComparator).');
    }

    /**
     * @return array<int, string>
     */
    private function getAlterTableSQL(TableDiff $tableDiff, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): array
    {
        if (method_exists($platform, 'getAlterTableSQL')) {
            return $platform->getAlterTableSQL($tableDiff);
        }

        return [];
    }
}
