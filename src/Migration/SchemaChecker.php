<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Throwable;

use function is_object;
use function strlen;

/**
 * Helper to check if tables, columns, indexes, primary keys or foreign keys exist.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaChecker
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getSchemaManager(): AbstractSchemaManager
    {
        return $this->connection->createSchemaManager();
    }

    public function tableExists(string $tableName): bool
    {
        try {
            $normalized = $this->normalizeIdentifier($tableName);

            return $this->getSchemaManager()->tablesExist([$normalized]);
        } catch (Throwable) {
            return false;
        }
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        try {
            if (!$this->tableExists($tableName)) {
                return false;
            }
            $table = $this->getSchemaManager()->introspectTable($this->normalizeIdentifier($tableName));
            $col   = $this->normalizeIdentifier($columnName);

            return $table->hasColumn($col);
        } catch (Throwable) {
            return false;
        }
    }

    public function indexExists(string $tableName, string $indexName): bool
    {
        try {
            if (!$this->tableExists($tableName)) {
                return false;
            }
            $table = $this->getSchemaManager()->introspectTable($this->normalizeIdentifier($tableName));

            return $table->hasIndex($this->normalizeIdentifier($indexName));
        } catch (Throwable) {
            return false;
        }
    }

    public function hasPrimaryKey(string $tableName): bool
    {
        try {
            if (!$this->tableExists($tableName)) {
                return false;
            }
            $table = $this->getSchemaManager()->introspectTable($this->normalizeIdentifier($tableName));

            return $table->getPrimaryKey() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function foreignKeyExists(string $tableName, string $foreignKeyName): bool
    {
        try {
            if (!$this->tableExists($tableName)) {
                return false;
            }
            $table = $this->getSchemaManager()->introspectTable($this->normalizeIdentifier($tableName));

            return $table->hasForeignKey($this->normalizeIdentifier($foreignKeyName));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function listTableColumns(string $tableName): array
    {
        try {
            if (!$this->tableExists($tableName)) {
                return [];
            }
            $table = $this->getSchemaManager()->introspectTable($this->normalizeIdentifier($tableName));
            $names = [];
            foreach ($table->getColumns() as $column) {
                $names[] = SchemaAssetName::get($column);
            }

            return $names;
        } catch (Throwable) {
            return [];
        }
    }

    private function normalizeIdentifier(string $name): string
    {
        $trimmed = trim($name);
        if (strlen($trimmed) >= 2) {
            $first = $trimmed[0];
            $last  = $trimmed[strlen($trimmed) - 1];
            if (($first === '`' && $last === '`') || ($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($trimmed, 1, -1);
            }
        }

        return $trimmed;
    }
}
