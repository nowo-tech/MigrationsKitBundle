<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests that use a connection with MySQL platform to cover SQL generation
 * for DROP PRIMARY KEY and DROP FOREIGN KEY (not supported on SQLite).
 */
class CreateTablesServiceMySQLPlatformTest extends TestCase
{
    private function createServiceWithMySQLPlatform(): CreateTablesService
    {
        $platform = new MySQLPlatform();
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('createComparator')->willReturn(
            new \Doctrine\DBAL\Schema\Comparator($platform)
        );
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $parser = new SchemaDefinitionParser();
        return new CreateTablesService($connection, $parser);
    }

    public function testApplyDropPrimaryKeyEmitsSqlWithMySQLPlatform(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema = new Schema();
        $table = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'users' => [MDK::DROP_PRIMARY_KEYS => []],
            ],
        ];
        $sqls = $service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsStringIgnoringCase('DROP', implode(' ', $sqls));
        self::assertStringContainsString('users', implode(' ', $sqls));
    }

    public function testApplyDropForeignKeyEmitsSqlWithMySQLPlatform(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema = new Schema();
        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], [], 'fk_orders_user_id');
        $def = [
            MDK::TABLES => [
                'orders' => [MDK::DROP_FOREIGN_KEYS => ['fk_orders_user_id']],
            ],
        ];
        $sqls = $service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsStringIgnoringCase('FOREIGN', implode(' ', $sqls));
    }

    /** Change PK on existing table: drop current PK + add new PK. */
    public function testApplyChangePrimaryKeyEmitsSqlWithMySQLPlatform(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema = new Schema();
        $table = $schema->createTable('change_pk');
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addColumn('code', 'string', ['length' => 32, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'change_pk' => [
                    MDK::PRIMARY_KEY => [['columns' => ['code']]],
                ],
            ],
        ];
        $sqls = $service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        $sql = implode(' ', $sqls);
        self::assertStringContainsString('change_pk', $sql);
        self::assertStringContainsStringIgnoringCase('PRIMARY', $sql);
    }
}
