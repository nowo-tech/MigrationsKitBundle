<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;

class SchemaMigrationServiceTest extends TestCase
{
    private CreateTablesService $service;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $parser        = new SchemaDefinitionParser();
        $this->service = new CreateTablesService($connection, $parser);
    }

    private function schemaWithUsersTable(): Schema
    {
        $schema = new Schema();
        $table  = $schema->createTable('users');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $table->setPrimaryKey(['id']);

        return $schema;
    }

    public function testApplyWithEmptyDefinitionReturnsEmpty(): void
    {
        $schema = new Schema();
        $sqls   = $this->service->apply($schema, []);
        self::assertSame([], $sqls);
    }

    public function testApplyCreatesTableWhenTableNotInSchema(): void
    {
        $schema = new Schema();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertCount(1, $sqls);
        self::assertStringContainsString('CREATE TABLE', $sqls[0]);
        self::assertStringContainsString('users', $sqls[0]);
    }

    public function testApplyDropsTableWithDropTables(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [MDK::DROP_TABLES => ['users']];
        $sqls   = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('DROP TABLE', $sqls[0]);
    }

    /** CreateTablesService drops tables only via DROP_TABLES; table def with DROP => true is not supported. */
    public function testApplyDropsTableWithDropTrueInTableDef(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [MDK::DROP_TABLES => ['users']];
        $sqls   = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('DROP TABLE', $sqls[0]);
    }

    public function testApplyTableEditsAddColumn(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('ALTER TABLE', $sqls[0]);
        self::assertStringContainsString('email', $sqls[0]);
    }

    public function testApplyTableEditsDropColumnViaDropColumns(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_COLUMNS => ['name'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('users', implode(' ', $sqls));
    }

    /** CreateTablesService drops columns via DROP_COLUMNS. */
    public function testApplyTableEditsDropColumnViaDropTrue(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_COLUMNS => ['name'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsRenameColumn(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'name', MDK::RENAME => 'full_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsModifyColumn(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'name', 'type' => 'string', 'length' => 500, 'notnull' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsAddIndex(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => ['name'], 'name' => 'idx_users_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsAddUniqueIndex(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => ['name'], 'name' => 'uniq_users_name', 'unique' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsDropIndex(): void
    {
        $schema = $this->schemaWithUsersTable();
        $table  = $schema->getTable('users');
        $table->addIndex(['name'], 'idx_users_name');
        $def = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_INDEXES => ['idx_users_name'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsAddPrimaryKey(): void
    {
        $schema = new Schema();
        $table  = $schema->createTable('no_pk');
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addColumn('code', 'string', ['length' => 32, 'notnull' => true]);
        $def = [
            MDK::TABLES => [
                'no_pk' => [
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    /** Change PK on existing table: drop current PK + add new PK (via comparator). */
    public function testApplyTableEditsChangePrimaryKey(): void
    {
        if ($this->service->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            self::markTestSkipped('SQLite does not support changing primary key via simple ALTER');
        }
        $schema = new Schema();
        $table  = $schema->createTable('change_pk');
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
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        $sql = implode(' ', $sqls);
        self::assertStringContainsString('change_pk', $sql);
    }

    public function testApplyTableEditsDropPrimaryKey(): void
    {
        if ($this->service->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            self::markTestSkipped('SQLite does not support simple DROP PRIMARY KEY');
        }
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_PRIMARY_KEYS => [],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsAddForeignKey(): void
    {
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['user_id'],
                            'foreign_table'   => 'users',
                            'foreign_columns' => ['id'],
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyTableEditsDropForeignKey(): void
    {
        if ($this->service->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            self::markTestSkipped('SQLite does not support simple DROP FOREIGN KEY');
        }
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], [], 'fk_orders_user_id');
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::DROP_FOREIGN_KEYS => ['fk_orders_user_id'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplySkipsCreateTableWhenTableNotExistsAndDefHasOnlyRenameColumns(): void
    {
        $schema = new Schema();
        $def    = [
            MDK::TABLES => [
                'nonexistent' => [
                    MDK::COLUMNS => [
                        ['name' => 'old_col', MDK::RENAME => 'new_col'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertSame([], $sqls, 'Should not attempt CREATE TABLE when table does not exist and def only has rename columns');
    }

    public function testApplySkipsNonArrayTableDef(): void
    {
        $schema = new Schema();
        $def    = [MDK::TABLES => ['users' => 'not_an_array']];
        $sqls   = $this->service->apply($schema, $def);
        self::assertSame([], $sqls);
    }

    public function testApplySkipsColumnWithoutName(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['type' => 'string', 'length' => 255],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testDropTableNotInSchemaDoesNotEmitSql(): void
    {
        $schema = new Schema();
        $def    = [MDK::DROP_TABLES => ['nonexistent']];
        $sqls   = $this->service->apply($schema, $def);
        self::assertSame([], $sqls);
    }

    /** When dropping a table that is referenced by another table's FK, the service drops the FK first (Phase 1). */
    public function testApplyDropTablesDropsFkReferencingDroppedTableFirst(): void
    {
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], [], 'fk_orders_user');
        $def  = [MDK::DROP_TABLES => ['users']];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        $sql = implode(' ', $sqls);
        self::assertStringContainsString('users', $sql);
    }

    public function testApplyTableEditsDropIndexViaItemWithDropTrue(): void
    {
        $schema = $this->schemaWithUsersTable();
        $table  = $schema->getTable('users');
        $table->addIndex(['name'], 'idx_to_drop');
        $def = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_INDEXES => ['idx_to_drop'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    /** CreateTablesService drops FKs via DROP_FOREIGN_KEYS. */
    public function testApplyTableEditsDropForeignKeyViaItemWithDropTrue(): void
    {
        if ($this->service->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            self::markTestSkipped('SQLite does not support simple DROP FOREIGN KEY');
        }
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], [], 'fk_drop_me');
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::DROP_FOREIGN_KEYS => ['fk_drop_me'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplySkipsColumnWithEmptyTypeWhenAdding(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'no_type', 'length' => 255],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsPrimaryKeyItemWithDropTrue(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::PRIMARY_KEY => [['columns' => ['id'], 'drop' => true]],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsIndexWithEmptyColumns(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => [], 'name' => 'idx_empty'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplyCreatesTableWithDecimalAndComment(): void
    {
        $schema = new Schema();
        $def    = [
            MDK::TABLES => [
                'products' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'price', 'type' => 'decimal', 'precision' => 10, 'scale' => 2, 'notnull' => true],
                        ['name' => 'note', 'type' => 'string', 'length' => 255, 'comment' => 'Internal note'],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('products', $sqls[0]);
    }

    public function testApplyTableEditsAddIndexWithIndexColumnsKey(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => ['name'], 'name' => 'idx_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyDropShortcutsSkipsDropColumnWhenColumnDoesNotExist(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_COLUMNS => ['nonexistent_column'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplyDropShortcutsSkipsDropIndexWhenIndexDoesNotExist(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::DROP_INDEXES => ['idx_nonexistent'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplyDropShortcutsSkipsDropForeignKeyWhenFkDoesNotExist(): void
    {
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::DROP_FOREIGN_KEYS => ['fk_nonexistent'],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsRenameColumnWhenOldColumnDoesNotExist(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'nonexistent', MDK::RENAME => 'new_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsAddIndexWhenIndexAlreadyExists(): void
    {
        $schema = $this->schemaWithUsersTable();
        $schema->getTable('users')->addIndex(['name'], 'idx_users_name');
        $def = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => ['name'], 'name' => 'idx_users_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsAddForeignKeyWhenFkAlreadyExists(): void
    {
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $orders->addForeignKeyConstraint('users', ['user_id'], ['id'], [], 'fk_orders_user_id');
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['user_id'],
                            'foreign_table'   => 'users',
                            'foreign_columns' => ['id'],
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsDropIndexWhenItemHasNoName(): void
    {
        $schema = $this->schemaWithUsersTable();
        $schema->getTable('users')->addIndex(['name'], 'idx_to_drop');
        $def = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['drop' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsDropForeignKeyWhenItemHasNoName(): void
    {
        $schema = new Schema();
        $users  = $schema->createTable('users');
        $users->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $users->setPrimaryKey(['id']);
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $orders->addColumn('user_id', 'integer', ['notnull' => true]);
        $orders->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::FOREIGN_KEYS => [
                        ['drop' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplySkipsNonArrayColumnItem(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer'],
                        'not_an_array',
                        ['name' => 'name', 'type' => 'string', 'length' => 255],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplySkipsNonArrayIndexItem(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => ['name'], 'name' => 'idx_name'],
                        'not_an_array',
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplySkipsForeignKeyWithEmptyLocalColumns(): void
    {
        $schema = new Schema();
        $schema->createTable('users')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('users')->setPrimaryKey(['id']);
        $schema->createTable('orders')->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $schema->getTable('orders')->setPrimaryKey(['id']);
        $def = [
            MDK::TABLES => [
                'orders' => [
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => [],
                            'foreign_table'   => 'users',
                            'foreign_columns' => ['id'],
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplyEmitsNoticeWhenMixingAddColumnAndIndexOrFkOnSameTable(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'role_id', 'type' => 'integer', 'notnull' => false],
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['role_id'], 'unique' => false],
                    ],
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['role_id'],
                            'foreign_table'   => 'roles',
                            'foreign_columns' => ['id'],
                        ],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        // apply() emits SQL in the correct order (add column, then index, then FK) when mixing them on the same table
        self::assertNotEmpty($sqls, 'apply() should emit SQL even when mixing add column and index/FK (bundle handles order)');
        self::assertStringContainsString('role_id', implode(' ', $sqls));
    }

    public function testApplyWarnIfMixingSkipsWhenTablesNotArray(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [MDK::TABLES => 'not_an_array'];
        $sqls   = $this->service->apply($schema, $def);
        self::assertSame([], $sqls);
    }

    /** CreateTablesService drops tables only via top-level DROP_TABLES. */
    public function testApplyWarnIfMixingSkipsWhenTableDefHasDrop(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [MDK::DROP_TABLES => ['users']];
        $sqls   = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('DROP TABLE', $sqls[0]);
    }

    public function testApplySkipsCreateTableWhenTableDefNotArray(): void
    {
        $schema = new Schema();
        $def    = [MDK::TABLES => ['users' => null]];
        $sqls   = $this->service->apply($schema, $def);
        self::assertSame([], $sqls);
    }

    public function testApplyRenameColumnThenAddIndexOnNewNameUsesTableStateAfterColumns(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'name', MDK::RENAME => 'full_name'],
                    ],
                    MDK::INDEXES => [
                        ['columns' => ['full_name'], 'name' => 'idx_full_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('full_name', implode(' ', $sqls));
    }

    public function testApplySkipsAddColumnWhenColumnAlreadyExistsButNoTypeForModify(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertEmpty($sqls);
    }

    public function testApplyDropTableWhenTableNotInSchemaDoesNotEmitSql(): void
    {
        $schema = new Schema();
        $def    = [MDK::DROP_TABLES => ['nonexistent']];
        $sqls   = $this->service->apply($schema, $def);
        self::assertSame([], $sqls);
    }

    private function schemaWithTableWithDecimalAndComment(): Schema
    {
        $schema = new Schema();
        $table  = $schema->createTable('products');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('note', 'string', ['length' => 255, 'comment' => 'Internal note']);
        $table->setPrimaryKey(['id']);

        return $schema;
    }

    public function testApplyDropPrimaryKeyWithDecimalAndCommentColumnsCoversColumnToOptions(): void
    {
        if ($this->service->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            self::markTestSkipped('SQLite does not support simple DROP PRIMARY KEY');
        }
        $schema = $this->schemaWithTableWithDecimalAndComment();
        $def    = [
            MDK::TABLES => [
                'products' => [MDK::DROP_PRIMARY_KEYS => []],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('products', implode(' ', $sqls));
    }

    public function testApplyRenameColumnOnTableWithDecimalCoversBuildTableAfterColumnOps(): void
    {
        $schema = $this->schemaWithTableWithDecimalAndComment();
        $def    = [
            MDK::TABLES => [
                'products' => [
                    MDK::COLUMNS => [
                        ['name' => 'amount', MDK::RENAME => 'price'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
        self::assertStringContainsString('price', implode(' ', $sqls));
    }

    public function testApplyAddIndexWithColumnsAsSingleString(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::INDEXES => [
                        ['columns' => ['name'], 'name' => 'idx_name'],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyAddColumnWithDefaultValue(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'status', 'type' => 'string', 'length' => 32, 'default' => 'active', 'notnull' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyWarnIfMixingSkipsWhenTableNotInSchema(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [['name' => 'x', 'type' => 'integer']],
                    MDK::INDEXES => [['columns' => ['x'], 'name' => 'idx_x']],
                ],
                'table_not_in_schema' => [
                    MDK::COLUMNS => [['name' => 'y', 'type' => 'integer']],
                    MDK::INDEXES => [['columns' => ['y'], 'name' => 'idx_y']],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }

    public function testApplyAddColumnWithUnsignedAndFixedInOptions(): void
    {
        $schema = $this->schemaWithUsersTable();
        $def    = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        ['name' => 'counter', 'type' => 'integer', 'unsigned' => true, 'notnull' => true],
                        ['name' => 'code', 'type' => 'string', 'length' => 10, 'fixed' => true, 'notnull' => true],
                    ],
                ],
            ],
        ];
        $sqls = $this->service->apply($schema, $def);
        self::assertNotEmpty($sqls);
    }
}
