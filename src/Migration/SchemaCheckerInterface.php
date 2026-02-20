<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use Doctrine\DBAL\Connection;

/**
 * Contract for schema checks (table/column/index/FK existence, row lookup).
 * Allows mocking in unit tests when the concrete implementation is final.
 */
interface SchemaCheckerInterface
{
    public function tableExists(string $tableName): bool;

    public function columnExists(string $tableName, string $columnName): bool;

    public function indexExists(string $tableName, string $indexName): bool;

    public function foreignKeyExists(string $tableName, string $fkName): bool;

    /**
     * @param array<string, mixed> $conditions
     */
    public function rowExists(string $table, array $conditions): bool;

    public function getConnection(): Connection;
}
