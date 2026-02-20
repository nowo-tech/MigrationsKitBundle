<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use PHPUnit\Framework\TestCase;

class SchemaCheckerTest extends TestCase
{
    private Connection $connection;
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->connection = $this->createMock(Connection::class);
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
        $col = $this->createMock(Column::class);
        $col->method('getName')->willReturn('email');
        $table->method('getColumns')->willReturn([$col]);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->columnExists('users', 'email'));
    }

    public function testColumnExistsReturnsFalseWhenColumnDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $col = $this->createMock(Column::class);
        $col->method('getName')->willReturn('name');
        $table->method('getColumns')->willReturn([$col]);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->columnExists('users', 'email'));
    }

    public function testIndexExistsReturnsTrueWhenIndexExists(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $index = $this->createMock(Index::class);
        $index->method('getName')->willReturn('idx_email');
        $table->method('getIndexes')->willReturn([$index]);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertTrue($checker->indexExists('users', 'idx_email'));
    }

    public function testListTableColumnsReturnsEmptyWhenTableDoesNotExist(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $checker = new SchemaChecker($this->connection);
        self::assertSame([], $checker->listTableColumns('users'));
    }

    public function testListTableColumnsReturnsColumnNames(): void
    {
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(Table::class);
        $c1 = $this->createMock(Column::class);
        $c1->method('getName')->willReturn('id');
        $c2 = $this->createMock(Column::class);
        $c2->method('getName')->willReturn('email');
        $table->method('getColumns')->willReturn([$c1, $c2]);
        $this->schemaManager->method('introspectTable')->with('users')->willReturn($table);

        $checker = new SchemaChecker($this->connection);
        self::assertSame(['id', 'email'], $checker->listTableColumns('users'));
    }

    public function testWithConnectionReturnsNewInstance(): void
    {
        $otherConnection = $this->createMock(Connection::class);
        $checker = new SchemaChecker($this->connection);
        $newChecker = $checker->withConnection($otherConnection);

        self::assertNotSame($checker, $newChecker);
        self::assertInstanceOf(SchemaChecker::class, $newChecker);
    }

    public function testRowExistsReturnsFalseWhenConditionsEmpty(): void
    {
        $checker = new SchemaChecker($this->connection);
        self::assertFalse($checker->rowExists('settings', []));
    }

    public function testGetConnectionReturnsInjectedConnection(): void
    {
        $checker = new SchemaChecker($this->connection);
        self::assertSame($this->connection, $checker->getConnection());
    }
}
