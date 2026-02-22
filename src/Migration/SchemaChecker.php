<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use RuntimeException;
use Throwable;

use function count;

/**
 * Service to check database schema (table/column/index existence).
 *
 * Compatible with DBAL 2.x (getSchemaManager), 3.x and 4.x (createSchemaManager).
 * Use this inside Doctrine Migrations: inject the connection or use the service
 * via a custom migration factory.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaChecker implements SchemaCheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Get schema manager (DBAL 2.x vs 3.x/4.x compatible).
     */
    public function getSchemaManager(): AbstractSchemaManager
    {
        if (method_exists($this->connection, 'createSchemaManager')) {
            return $this->connection->createSchemaManager();
        }
        if (method_exists($this->connection, 'getSchemaManager')) {
            $callable = [$this->connection, 'getSchemaManager'];

            return $callable();
        }
        throw new RuntimeException('Unable to get schema manager: neither createSchemaManager() nor getSchemaManager() is available.');
    }

    /**
     * Check if a table exists.
     *
     * @param string $tableName Table name (optionally quoted; will be normalized)
     */
    public function tableExists(string $tableName): bool
    {
        $tableName = $this->normalizeIdentifier($tableName);
        try {
            return $this->getSchemaManager()->tablesExist([$tableName]);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if a column exists in a table.
     *
     * @param string $tableName Table name
     * @param string $columnName Column name
     */
    public function columnExists(string $tableName, string $columnName): bool
    {
        $tableName  = $this->normalizeIdentifier($tableName);
        $columnName = $this->normalizeIdentifier($columnName);
        try {
            $sm = $this->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return false;
            }
            $columns = method_exists($sm, 'introspectTable')
                ? $sm->introspectTable($tableName)->getColumns()
                : $sm->listTableColumns($tableName);
            foreach ($columns as $column) {
                $name = $this->getColumnName($column);
                if (strcasecmp($name, $columnName) === 0) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if the table has a primary key.
     *
     * @param string $tableName Table name
     */
    public function hasPrimaryKey(string $tableName): bool
    {
        $tableName = $this->normalizeIdentifier($tableName);
        try {
            $sm = $this->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return false;
            }
            $indexes = method_exists($sm, 'introspectTable')
                ? $sm->introspectTable($tableName)->getIndexes()
                : $sm->listTableIndexes($tableName);
            foreach ($indexes as $index) {
                if (method_exists($index, 'isPrimary') && $index->isPrimary()) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if an index exists on a table.
     *
     * @param string $tableName Table name
     * @param string $indexName Index name
     */
    public function indexExists(string $tableName, string $indexName): bool
    {
        $tableName = $this->normalizeIdentifier($tableName);
        $indexName = $this->normalizeIdentifier($indexName);
        try {
            $sm = $this->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return false;
            }
            $indexes = method_exists($sm, 'introspectTable')
                ? $sm->introspectTable($tableName)->getIndexes()
                : $sm->listTableIndexes($tableName);
            foreach ($indexes as $index) {
                $name = $this->getIndexName($index);
                if (strcasecmp($name, $indexName) === 0) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if a foreign key exists on a table.
     *
     * @param string $tableName Table name
     * @param string $fkName Foreign key constraint name
     */
    public function foreignKeyExists(string $tableName, string $fkName): bool
    {
        $tableName = $this->normalizeIdentifier($tableName);
        $fkName    = $this->normalizeIdentifier($fkName);
        try {
            $sm = $this->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return false;
            }
            $fks = method_exists($sm, 'introspectTable')
                ? $sm->introspectTable($tableName)->getForeignKeys()
                : $sm->listTableForeignKeys($tableName);
            foreach ($fks as $fk) {
                $name = $fk->getName();
                if (strcasecmp($name, $fkName) === 0) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * List column names for a table (empty if table does not exist).
     *
     * @return array<int, string>
     */
    public function listTableColumns(string $tableName): array
    {
        $tableName = $this->normalizeIdentifier($tableName);
        try {
            $sm = $this->getSchemaManager();
            if (!$sm->tablesExist([$tableName])) {
                return [];
            }
            $columns = method_exists($sm, 'introspectTable')
                ? $sm->introspectTable($tableName)->getColumns()
                : $sm->listTableColumns($tableName);
            $names = [];
            foreach ($columns as $column) {
                $names[] = $this->getColumnName($column);
            }

            return $names;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Check if a row exists in a table matching the given conditions (key => value).
     * Uses parameterized query; safe for any value types.
     *
     * @param string $table Table name
     * @param array<string, mixed> $conditions Column => value (all must match)
     */
    public function rowExists(string $table, array $conditions): bool
    {
        if ($conditions === []) {
            return false;
        }
        $table = $this->normalizeIdentifier($table);
        try {
            $platform    = $this->connection->getDatabasePlatform();
            $quotedTable = $platform->quoteIdentifier($table);
            $wheres      = [];
            $params      = [];
            $types       = [];
            foreach ($conditions as $column => $value) {
                $col      = $this->normalizeIdentifier((string) $column);
                $wheres[] = $platform->quoteIdentifier($col) . ' = ?';
                $params[] = $value;
            }
            $types  = array_fill(0, count($params), ParameterType::STRING);
            $sql    = 'SELECT 1 FROM ' . $quotedTable . ' WHERE ' . implode(' AND ', $wheres) . ' LIMIT 1';
            $result = $this->connection->executeQuery($sql, $params, $types);

            return $result->fetchOne() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the DBAL connection (e.g. for building parameterized SQL in migrations).
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Create a checker for another connection (e.g. in migrations with multiple connections).
     */
    public function withConnection(Connection $connection): self
    {
        return new self($connection);
    }

    private function normalizeIdentifier(string $name): string
    {
        return trim($name, '`"\'');
    }

    private function getColumnName(Column $column): string
    {
        return $column->getName();
    }

    private function getIndexName(Index $index): string
    {
        return $index->getName();
    }
}
