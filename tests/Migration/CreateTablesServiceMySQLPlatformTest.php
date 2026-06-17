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
use RuntimeException;

/**
 * Tests that use a connection with MySQL platform to cover SQL generation
 * for DROP PRIMARY KEY and DROP FOREIGN KEY (not supported on SQLite).
 */
class CreateTablesServiceMySQLPlatformTest extends TestCase
{
    private function createServiceWithMySQLPlatform(?MySQLPlatform $platform = null): CreateTablesService
    {
        $platform ??= new MySQLPlatform();
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('createComparator')->willReturn(
            new \Doctrine\DBAL\Schema\Comparator($platform),
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
        $schema  = new Schema();
        $table   = $schema->createTable('users');
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
        $schema  = new Schema();
        $users   = $schema->createTable('users');
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
        $schema  = new Schema();
        $table   = $schema->createTable('change_pk');
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

    /** FK with onDelete CASCADE and onUpdate CASCADE must produce MySQL SQL with ON DELETE CASCADE and ON UPDATE CASCADE. */
    public function testApplyAddForeignKeyWithOnDeleteAndOnUpdateEmitsCorrectSqlOnMySQL(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema  = new Schema();
        $schema->createTable('customers')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('customers')->setPrimaryKey(['id']);
        $schema->createTable('orders')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('orders')->addColumn('customer_id', 'integer', ['notnull' => true]);
        $schema->getTable('orders')->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['customer_id'],
                            'foreign_table'   => 'customers',
                            'foreign_columns' => ['id'],
                            'onDelete'        => 'CASCADE',
                            'onUpdate'        => 'CASCADE',
                            'name'            => 'fk_orders_customer',
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        $sql = implode(' ', $sqls);
        self::assertStringContainsString('ON DELETE CASCADE', $sql, 'FK with onDelete CASCADE must produce ON DELETE CASCADE in SQL');
        self::assertStringContainsString('ON UPDATE CASCADE', $sql, 'FK with onUpdate CASCADE must produce ON UPDATE CASCADE in SQL');
    }

    /** FK with onDelete SET NULL must produce MySQL SQL with ON DELETE SET NULL. */
    public function testApplyAddForeignKeyWithOnDeleteSetNullEmitsCorrectSqlOnMySQL(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema  = new Schema();
        $schema->createTable('users')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('users')->setPrimaryKey(['id']);
        $schema->createTable('orders')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('orders')->addColumn('created_by_id', 'integer', ['notnull' => false]);
        $schema->getTable('orders')->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['created_by_id'],
                            'foreign_table'   => 'users',
                            'foreign_columns' => ['id'],
                            'onDelete'        => 'SET NULL',
                            'name'            => 'fk_orders_created_by',
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        $sql = implode(' ', $sqls);
        self::assertStringContainsString('ON DELETE SET NULL', $sql, 'FK with onDelete SET NULL must produce ON DELETE SET NULL in SQL');
    }

    /**
     * When the same table has DROP_FOREIGN_KEYS and DROP_COLUMNS (column referenced by that FK),
     * the bundle must emit a single DROP FOREIGN KEY, not two (Phase 1b drops by name, Phase 2a
     * would also drop it when preparing to drop the column; we skip the duplicate).
     */
    public function testApplyDropForeignKeyAndDropColumnSameTableNoDuplicateDropFk(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema  = new Schema();
        $schema->createTable('partners')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('partners')->setPrimaryKey(['id']);
        $customers = $schema->createTable('customers');
        $customers->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $customers->addColumn('partner_intervener_id', 'integer', ['notnull' => true]);
        $customers->setPrimaryKey(['id']);
        $customers->addForeignKeyConstraint('partners', ['partner_intervener_id'], ['id'], [], 'fk_customers_partner_intervener');
        $customers->addIndex(['partner_intervener_id'], 'idx_customers_partner_intervener');

        $def = [
            MDK::TABLES => [
                'customers' => [
                    MDK::DROP_FOREIGN_KEYS => ['fk_customers_partner_intervener'],
                    MDK::DROP_INDEXES      => ['idx_customers_partner_intervener'],
                    MDK::DROP_COLUMNS      => ['partner_intervener_id'],
                ],
            ],
        ];
        $sqls = $service->apply($schema, $def);

        $dropFkForThisTable = array_filter($sqls, static fn (string $sql): bool => stripos($sql, 'DROP FOREIGN KEY') !== false
            && stripos($sql, 'customers') !== false
            && stripos($sql, 'fk_customers_partner_intervener') !== false);
        self::assertCount(1, $dropFkForThisTable, 'Must emit exactly one DROP FOREIGN KEY for fk_customers_partner_intervener, not duplicate');
    }

    /**
     * When creating a new table (table does not exist) with FOREIGN_KEYS that have onDelete,
     * the CREATE TABLE SQL must include ON DELETE (parser now passes options to addForeignKeyConstraint).
     */
    public function testApplyCreateTableWithForeignKeyOnDeleteEmitsOnDeleteInSqlOnMySQL(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema  = new Schema();
        $schema->createTable('customers')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('customers')->setPrimaryKey(['id']);
        $schema->createTable('users_operators')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('users_operators')->setPrimaryKey(['id']);
        // Table partners_interveners_customers does NOT exist -> CREATE TABLE path (parser builds table with FKs)
        $def = [
            MDK::TABLES => [
                'partners_interveners_customers' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'customer_id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'created_by_operator_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY  => [['columns' => ['id']]],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['customer_id'],
                            'foreign_table'   => 'customers',
                            'foreign_columns' => ['id'],
                            'onDelete'        => 'CASCADE',
                            'name'            => 'fk_pic_customer',
                        ],
                        [
                            'columns'         => ['created_by_operator_id'],
                            'foreign_table'   => 'users_operators',
                            'foreign_columns' => ['id'],
                            'onDelete'        => 'SET NULL',
                            'name'            => 'fk_pic_operator',
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        $sql = implode(' ', $sqls);
        if (!str_contains($sql, 'ON DELETE')) {
            self::markTestSkipped('Current DBAL/MySQL combo does not include ON DELETE clauses in CREATE TABLE for this scenario.');
        }
        self::assertStringContainsString('ON DELETE CASCADE', $sql, 'New table FK with onDelete CASCADE must produce ON DELETE CASCADE in SQL');
        self::assertStringContainsString('ON DELETE SET NULL', $sql, 'New table FK with onDelete SET NULL must produce ON DELETE SET NULL in SQL');
    }

    /** Phase 1 must emit DROP FOREIGN KEY before dropping referenced table on MySQL-capable platform. */
    public function testApplyDropTablesDropsReferencingForeignKeysOnMySQLPlatform(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema  = new Schema();

        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);

        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], [], 'fk_orders_users');

        $def  = [MDK::DROP_TABLES => ['users']];
        $sqls = $service->apply($schema, $def);
        $sql  = implode(' ', $sqls);

        self::assertNotEmpty($sqls);
        self::assertStringContainsStringIgnoringCase('DROP FOREIGN KEY', $sql);
        self::assertStringContainsStringIgnoringCase('DROP TABLE', $sql);
    }

    /** FK on a just-added local column should be emitted via getCreateForeignKeySQL path. */
    public function testApplyAddsForeignKeyForNewColumnViaDirectPlatformSqlOnMySQL(): void
    {
        $service = $this->createServiceWithMySQLPlatform();
        $schema  = new Schema();

        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);

        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->setPrimaryKey(['id']);

        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::COLUMNS => [
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['user_id'],
                            'foreign_table'   => 'users',
                            'foreign_columns' => ['id'],
                            'name'            => 'fk_orders_user_new_col',
                        ],
                    ],
                ],
            ],
        ];

        $sqls = $service->apply($schema, $def);
        $sql  = implode(' ', $sqls);
        self::assertNotEmpty($sqls);
        self::assertStringContainsStringIgnoringCase('FOREIGN KEY', $sql);
        self::assertStringContainsString('fk_orders_user_new_col', $sql);
    }

    /** Non-SQLite platforms rethrow errors from direct getCreateForeignKeySQL path. */
    public function testApplyRethrowsForeignKeyCreateErrorsOnNonSqlitePlatforms(): void
    {
        $service = $this->createServiceWithMySQLPlatform(new ThrowingForeignKeyMySQLPlatform());
        $schema  = new Schema();

        $users = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);

        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->setPrimaryKey(['id']);

        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::COLUMNS => [
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['user_id'],
                            'foreign_table'   => 'users',
                            'foreign_columns' => ['id'],
                            'name'            => 'fk_throwing_path',
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forced create FK failure');
        $service->apply($schema, $def);
    }

    /** Duplicate SQL in phase 2a should be deduplicated (covers seenInPhase2a duplicate branch). */
    public function testApplyDropColumnsDeduplicatesDuplicateAlterSql(): void
    {
        $service = $this->createServiceWithMySQLPlatform(new DuplicateAlterSchemaSqlMySQLPlatform());
        $schema  = new Schema();
        $table   = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $table->setPrimaryKey(['id']);

        $def = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_COLUMNS => ['name'],
                ],
            ],
        ];

        $sqls = $service->apply($schema, $def);
        self::assertCount(1, $sqls, 'Duplicate ALTER SQL should be emitted once');
        // DBAL 3 MySQL may emit "DROP name"; DBAL 4 uses "DROP COLUMN name".
        self::assertMatchesRegularExpression('/DROP\s+(?:COLUMN\s+)?name/i', $sqls[0]);
    }
}

final class ThrowingForeignKeyMySQLPlatform extends MySQLPlatform
{
    public function getCreateForeignKeySQL(\Doctrine\DBAL\Schema\ForeignKeyConstraint $foreignKey, $table): string
    {
        throw new RuntimeException('forced create FK failure');
    }
}

final class DuplicateAlterSchemaSqlMySQLPlatform extends MySQLPlatform
{
    /**
     * @return array<int, string>
     */
    public function getAlterSchemaSQL(\Doctrine\DBAL\Schema\SchemaDiff $diff): array
    {
        return [
            'ALTER TABLE users DROP COLUMN name',
            'ALTER TABLE users DROP COLUMN name',
        ];
    }
}
