<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Exception;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use PHPUnit\Framework\TestCase;

class SchemaCheckerTest extends TestCase
{
    private Connection $connection;
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->connection    = $this->createMock(Connection::class);
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
    }

    public function testTableExistsReturnsTrueWhenTableExists(): void
    {
        $this->schemaManager->expects(self::once())
            ->method('tablesExist')
            ->with(['users'])
            ->willReturn(true);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->tableExists('users'));
    }

    public function testTableExistsReturnsFalseWhenTableDoesNotExist(): void
    {
        $this->schemaManager->expects(self::once())
            ->method('tablesExist')
            ->with(['missing'])
            ->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->tableExists('missing'));
    }

    public function testTableExistsNormalizesQuotedNames(): void
    {
        $this->schemaManager->expects(self::once())
            ->method('tablesExist')
            ->with(['users']);

        $checker = new SchemaChecker($this->connection);
        $checker->tableExists('`users`');
    }

    public function testColumnExistsReturnsFalseWhenTableDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->columnExists('users', 'email'));
    }

    public function testColumnExistsReturnsTrueWhenColumnExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasColumn')->with('email')->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->columnExists('users', 'email'));
    }

    public function testColumnExistsReturnsFalseWhenColumnDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasColumn')->with('email')->willReturn(false);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->columnExists('users', 'email'));
    }

    public function testIndexExistsReturnsTrueWhenIndexExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasIndex')->with('idx_email')->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->indexExists('users', 'idx_email'));
    }

    public function testGetConnectionReturnsInjectedConnection(): void
    {
        $checker = new SchemaChecker($this->connection);
        self::assertSame($this->connection, $checker->getConnection());
    }

    public function testGetSchemaManagerReturnsManagerWhenCreateSchemaManagerExists(): void
    {
        $checker = new SchemaChecker($this->connection);
        self::assertSame($this->schemaManager, $checker->getSchemaManager());
    }

    public function testIndexExistsReturnsFalseWhenIndexDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasIndex')->with('idx_other')->willReturn(false);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->indexExists('users', 'idx_other'));
    }

    public function testIndexExistsReturnsFalseOnException(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->method('introspectTable')->willThrowException(new Exception('introspect error'));

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->indexExists('users', 'idx_x'));
    }

    public function testHasPrimaryKeyReturnsTrueWhenPrimaryKeyExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table   = $this->createMock(Table::class);
        $primary = $this->createMock(Index::class);
        $table->method('getPrimaryKey')->willReturn($primary);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->hasPrimaryKey('users'));
    }

    public function testHasPrimaryKeyReturnsFalseWhenNoPrimaryKey(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('getPrimaryKey')->willReturn(null);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->hasPrimaryKey('users'));
    }

    public function testHasPrimaryKeyReturnsFalseWhenTableDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->hasPrimaryKey('missing'));
    }

    public function testHasPrimaryKeyReturnsFalseOnException(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->method('introspectTable')->willThrowException(new Exception('DB error'));

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->hasPrimaryKey('users'));
    }

    public function testForeignKeyExistsReturnsTrueWhenFkExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasForeignKey')->with('fk_user')->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('orders')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->foreignKeyExists('orders', 'fk_user'));
    }

    public function testForeignKeyExistsReturnsFalseWhenFkDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasForeignKey')->with('fk_missing')->willReturn(false);
        $this->schemaManager->method('introspectTable')->with('orders')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->foreignKeyExists('orders', 'fk_missing'));
    }

    public function testForeignKeyExistsReturnsFalseWhenTableDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->foreignKeyExists('missing', 'fk_x'));
    }

    public function testForeignKeyExistsReturnsFalseOnException(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->method('introspectTable')->willThrowException(new Exception('error'));

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->foreignKeyExists('orders', 'fk_x'));
    }

    public function testTableExistsReturnsFalseOnException(): void
    {
        $this->schemaManager->method('tablesExist')->willThrowException(new Exception('introspect error'));

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->tableExists('any'));
    }

    public function testColumnExistsNormalizesQuotedColumnName(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasColumn')->with('email')->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->columnExists('users', '`email`'));
    }

    public function testColumnExistsReturnsFalseOnException(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->method('introspectTable')->willThrowException(new Exception('introspect error'));

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->columnExists('users', 'email'));
    }

    public function testIndexExistsReturnsFalseWhenTableDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->indexExists('missing', 'idx_x'));
    }

    public function testForeignKeyExistsNormalizesQuotedNames(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('hasForeignKey')->with('fk_user')->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('orders')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->foreignKeyExists('"orders"', '"fk_user"'));
    }

    public function testHasPrimaryKeyNormalizesQuotedTableName(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $table->method('getPrimaryKey')->willReturn($this->createMock(Index::class));
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->hasPrimaryKey("'users'"));
    }

    public function testListTableColumnsReturnsColumnNamesWhenTableExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $col1 = $this->createMock(Column::class);
        $col1->method('getName')->willReturn('id');
        $col2 = $this->createMock(Column::class);
        $col2->method('getName')->willReturn('email');
        $table = $this->createMock(Table::class);
        $table->method('getColumns')->willReturn([$col1, $col2]);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertSame(['id', 'email'], $checker->listTableColumns('users'));
    }

    public function testListTableColumnsReturnsEmptyWhenTableDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertSame([], $checker->listTableColumns('missing'));
    }

    public function testListTableColumnsReturnsEmptyOnException(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->method('introspectTable')->willThrowException(new Exception('introspect error'));

        $checker = new SchemaChecker($this->connection);
        self::assertSame([], $checker->listTableColumns('users'));
    }
}
