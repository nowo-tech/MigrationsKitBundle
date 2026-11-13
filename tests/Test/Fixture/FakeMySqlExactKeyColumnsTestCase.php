<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Nowo\MigrationsKitBundle\Test\ExactKeyColumnsTestCase;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumns;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumnsInterface;

use function str_contains;

/**
 * A bundle's own REQ-SEC-006 gate, with the bundle's test database replaced by a fake information_schema.
 *
 * ExactKeyColumnsTestCase ships in src/ for bundles and hosts to extend, so it is covered the way they use it: by
 * running its one final test method. Only the connection is faked, so the whole body - including the "declares
 * nothing" and "every column was skipped, this run checked nothing" guards - is exercised for real.
 *
 * Deliberately not named *Test.php: PHPUnit must not collect the failing variants as tests of their own.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
abstract class FakeMySqlExactKeyColumnsTestCase extends ExactKeyColumnsTestCase
{
    public int $createSchemaCalls = 0;

    /**
     * @return list<ExactKeyColumn>
     */
    abstract protected function declaredColumns(): array;

    /**
     * @return list<ExactKeyColumnsInterface>
     */
    protected function exactKeyColumnProviders(): array
    {
        return [new ExactKeyColumns(...$this->declaredColumns())];
    }

    protected function createSchema(Connection $connection): void
    {
        parent::createSchema($connection);
        ++$this->createSchemaCalls;
    }

    protected function createTestConnection(): ?Connection
    {
        $collations = $this->serverCollations();
        $tables     = $this->schemaTables();

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createPlatform());
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static fn (string $sql): array => str_contains($sql, 'COLLATIONS') ? $collations : $tables,
        );
        $connection->method('fetchAllAssociative')->willReturn($this->schemaColumns());

        return $connection;
    }

    protected function createPlatform(): AbstractPlatform
    {
        return new MySQLPlatform();
    }

    /**
     * @return list<string> binary NO PAD collations the fake server offers
     */
    protected function serverCollations(): array
    {
        return ['utf8mb4_0900_bin'];
    }

    /**
     * @return list<string> base tables that exist
     */
    protected function schemaTables(): array
    {
        return [];
    }

    /**
     * @return list<array<string, mixed>> information_schema.COLUMNS rows
     */
    protected function schemaColumns(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(string $table, string $column, ?string $collation, string $type = 'varchar(64)'): array
    {
        return ['table_name' => $table, 'column_name' => $column, 'collation_name' => $collation, 'column_type' => $type, 'is_nullable' => 'NO'];
    }
}
