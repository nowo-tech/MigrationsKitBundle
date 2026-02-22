<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Exception;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use Nowo\MigrationsKitBundle\Schema\SchemaSync;
use PHPUnit\Framework\TestCase;

class SchemaSyncTest extends TestCase
{
    /**
     * Create SchemaDiff with empty changes (DBAL 4 requires 8 args).
     */
    private static function createEmptySchemaDiff(): SchemaDiff
    {
        return new SchemaDiff([], [], [], [], [], [], [], []);
    }

    private function createSchemaCheckerWithMocks(
        Connection $connection,
        AbstractSchemaManager $schemaManager
    ): SchemaChecker {
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        return new SchemaChecker($connection);
    }

    public function testSyncCreatesNewTableWhenNotExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getCreateTableSQL')->willReturn(['CREATE TABLE users (id INT)']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn(self::createEmptySchemaDiff());
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $sync->sync($addSql, $definition);

        self::assertCount(1, $sqls);
        self::assertSame('CREATE TABLE users (id INT)', $sqls[0]);
    }

    public function testSyncSkipsTableWhenAlreadyExists(): void
    {
        $connection    = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn(self::createEmptySchemaDiff());
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $sync->sync($addSql, $definition);

        self::assertCount(0, $sqls);
    }

    public function testSyncSkipsInvalidTableDef(): void
    {
        $connection    = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn(self::createEmptySchemaDiff());
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $definition = [
            MDK::TABLES => [
                'empty'      => [],
                'no_columns' => [MDK::COLUMNS => []],
            ],
        ];

        $sync->sync($addSql, $definition);

        self::assertCount(0, $sqls);
    }

    public function testDiffReturnsCreateTableSqlForNewTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getCreateTableSQL')->willReturn(['CREATE TABLE users (id INT)']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn(self::createEmptySchemaDiff());
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $sql = $sync->diff($definition);

        self::assertCount(1, $sql);
        self::assertSame('CREATE TABLE users (id INT)', $sql[0]);
    }

    public function testDiffReturnsEmptyWhenTableExists(): void
    {
        $connection    = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn(self::createEmptySchemaDiff());
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $sql = $sync->diff($definition);

        self::assertCount(0, $sql);
    }

    public function testSyncWhenComparatorThrowsTableDoesNotExist(): void
    {
        $connection    = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')
            ->willThrowException(new Exception('There is no table with name x'));
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $sync->sync($addSql, [MDK::TABLES => []]);

        self::assertCount(0, $sqls);
    }

    public function testDiffWhenComparatorThrowsTableDoesNotExistReturnsCollectedSql(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getCreateTableSQL')->willReturn(['CREATE TABLE t (id INT)']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')
            ->willThrowException(new Exception('no table with name y'));
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $sql = $sync->diff($definition);

        self::assertCount(1, $sql);
    }

    public function testSyncWithDropTablesDropsTableNotInDefinition(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getDropTablesSQL')->willReturn(['DROP TABLE old_table']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator   = $this->createMock(Comparator::class);
        $droppedTable = new Table('old_table');
        $droppedTable->addColumn('id', 'integer');
        $schemaDiff = new SchemaDiff([], [], [], [], [$droppedTable], [], [], []);
        $comparator->method('compareSchemas')->willReturn($schemaDiff);
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $sync->sync($addSql, [MDK::TABLES => []], ['drop_tables' => true]);

        self::assertCount(1, $sqls);
        self::assertSame('DROP TABLE old_table', $sqls[0]);
    }

    public function testDiffWithDropTablesReturnsDropSql(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getDropTablesSQL')->willReturn(['DROP TABLE old_table']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator   = $this->createMock(Comparator::class);
        $droppedTable = new Table('old_table');
        $droppedTable->addColumn('id', 'integer');
        $schemaDiff = new SchemaDiff([], [], [], [], [$droppedTable], [], [], []);
        $comparator->method('compareSchemas')->willReturn($schemaDiff);
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sql = $sync->diff([MDK::TABLES => []], ['drop_tables' => true]);

        self::assertCount(1, $sql);
        self::assertSame('DROP TABLE old_table', $sql[0]);
    }

    public function testSyncUsesFallbackWhenGetCreateTableSQLThrowsTableDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getCreateTableSQL')
            ->willThrowException(new Exception('There is no table with name schema.users'));
        $platform->method('quoteIdentifier')->willReturnArgument(0);
        $platform->method('getColumnDeclarationSQL')->willReturn('id INT');
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);
        $schemaManager->method('introspectSchema')->willReturn(new Schema());
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn(self::createEmptySchemaDiff());
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $sync->sync($addSql, $definition);

        self::assertCount(1, $sqls);
        self::assertStringContainsString('CREATE TABLE', $sqls[0]);
        self::assertStringContainsString('users', $sqls[0]);
    }

    public function testSyncAppliesAlterTableWhenDiffHasModifiedTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getAlterTableSQL')->willReturn(['ALTER TABLE users ADD email VARCHAR(180)']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $currentSchema = new Schema();
        $oldTable      = $currentSchema->createTable('users');
        $oldTable->addColumn('id', 'integer');

        $addedColumn = new Column('email', Type::getType(Types::STRING), ['length' => 180]);
        $tableDiff   = new TableDiff($oldTable, [$addedColumn], [], [], [], [], [], [], [], [], []);

        $schemaDiff = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn($currentSchema);
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn($schemaDiff);
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id'    => ['type' => 'integer'],
                        'email' => ['type' => 'string', 'length' => 180],
                    ],
                ],
            ],
        ];

        $sync->sync($addSql, $definition);

        self::assertCount(1, $sqls);
        self::assertSame('ALTER TABLE users ADD email VARCHAR(180)', $sqls[0]);
    }

    public function testSyncContinuesWhenAlterTableThrowsTableDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getAlterTableSQL')->willThrowException(new Exception('There is no table with name xyz'));

        $currentSchema = new Schema();
        $currentSchema->createTable('users')->addColumn('id', 'integer');

        $oldTable    = $currentSchema->getTable('users');
        $addedColumn = new Column('email', Type::getType(Types::STRING), ['length' => 180]);
        $tableDiff   = new TableDiff($oldTable, [$addedColumn], [], [], [], [], [], [], [], [], []);
        $schemaDiff  = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn($currentSchema);
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn($schemaDiff);
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $sync->sync($addSql, [MDK::TABLES => ['users' => [MDK::COLUMNS => ['id' => ['type' => 'integer'], 'email' => ['type' => 'string', 'length' => 180]]]]]);

        self::assertCount(0, $sqls);
    }

    public function testDiffReturnsAlterSqlWhenDefinitionAddsColumn(): void
    {
        $connection = $this->createMock(Connection::class);
        $platform   = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('getAlterTableSQL')->willReturn(['ALTER TABLE users ADD email VARCHAR(180)']);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $currentSchema = new Schema();
        $currentSchema->createTable('users')->addColumn('id', 'integer');

        $oldTable    = $currentSchema->getTable('users');
        $addedColumn = new Column('email', Type::getType(Types::STRING), ['length' => 180]);
        $tableDiff   = new TableDiff($oldTable, [$addedColumn], [], [], [], [], [], [], [], [], []);
        $schemaDiff  = new SchemaDiff([], [], [], [$tableDiff], [], [], [], []);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectSchema')->willReturn($currentSchema);
        $comparator = $this->createMock(Comparator::class);
        $comparator->method('compareSchemas')->willReturn($schemaDiff);
        $schemaManager->method('createComparator')->willReturn($comparator);

        $schemaChecker = $this->createSchemaCheckerWithMocks($connection, $schemaManager);

        $parser = new SchemaDefinitionParser();
        $sync   = new SchemaSync($connection, $parser, $schemaChecker);

        $definition = [
            MDK::TABLES => [
                'users' => [
                    MDK::COLUMNS => [
                        'id'    => ['type' => 'integer'],
                        'email' => ['type' => 'string', 'length' => 180],
                    ],
                ],
            ],
        ];

        $sql = $sync->diff($definition);

        self::assertCount(1, $sql);
        self::assertSame('ALTER TABLE users ADD email VARCHAR(180)', $sql[0]);
    }
}
