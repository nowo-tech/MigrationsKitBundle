<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaCheckerInterface;
use PHPUnit\Framework\TestCase;

class MigrationDefinitionRunnerTest extends TestCase
{
    public function testRunCallsAddSqlForTableWhenTableDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->with('users')->willReturn(false);
        $checker->method('columnExists')->willReturn(false);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'tables' => [
                'users' => ['create_sql' => 'CREATE TABLE users (id INT)'],
            ],
            'columns' => [],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('CREATE TABLE users (id INT)', $sqls[0]);
    }

    public function testRunDoesNotCallAddSqlForTableWhenTableExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->with('users')->willReturn(true);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'tables' => [
                'users' => ['create_sql' => 'CREATE TABLE users (id INT)'],
            ],
            'columns' => [],
        ], $addSql);

        self::assertCount(0, $sqls);
    }

    public function testRunCallsAddSqlForColumnWhenColumnDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->willReturn(true);
        $checker->method('columnExists')
            ->willReturnMap([['users', 'email', false]]);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'tables' => [],
            'columns' => [
                ['table' => 'users', 'column' => 'email', 'add_sql' => 'ALTER TABLE users ADD email VARCHAR(180)'],
            ],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('ALTER TABLE users ADD email VARCHAR(180)', $sqls[0]);
    }

    public function testRunSkipsInvalidTableDefinition(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->willReturn(false);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'tables' => [
                'users' => [], // no create_sql
                'roles' => ['create_sql' => 'CREATE TABLE roles (id INT)'],
            ],
            'columns' => [],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('CREATE TABLE roles (id INT)', $sqls[0]);
    }

    public function testEnsureTableCallsAddSqlWhenTableDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->with('users')->willReturn(false);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->ensureTable('users', 'CREATE TABLE users (id INT)', $addSql);

        self::assertTrue($called);
    }

    public function testEnsureTableDoesNotCallAddSqlWhenTableExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->with('users')->willReturn(true);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->ensureTable('users', 'CREATE TABLE users (id INT)', $addSql);

        self::assertFalse($called);
    }

    public function testEnsureColumnCallsAddSqlWhenColumnDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('users', 'email')->willReturn(false);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->ensureColumn('users', 'email', 'ALTER TABLE users ADD email VARCHAR(180)', $addSql);

        self::assertTrue($called);
    }

    public function testRunDataInsertCallsAddSqlWithParams(): void
    {
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnArgument(0);

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->willReturn(true);
        $checker->method('columnExists')->willReturn(true);
        $checker->method('rowExists')->with('settings', ['key_name' => 'app.version'])->willReturn(false);
        $checker->method('getConnection')->willReturn($connection);

        $calls = [];
        $addSql = function (string $sql, array $params = []) use (&$calls): void {
            $calls[] = ['sql' => $sql, 'params' => $params];
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'data' => [
                ['insert' => ['table' => 'settings', 'row' => ['key_name' => 'app.version', 'value' => '1.0'], 'only_if_not_exists' => ['key_name' => 'app.version']]],
            ],
        ], $addSql);

        self::assertCount(1, $calls);
        self::assertStringContainsString('INSERT INTO settings', $calls[0]['sql']);
        self::assertSame(['app.version', '1.0'], $calls[0]['params']);
    }

    public function testRunDataInsertSkipsWhenOnlyIfNotExistsRowExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('rowExists')->with('settings', ['key_name' => 'app.version'])->willReturn(true);

        $calls = [];
        $addSql = function (string $sql, array $params = []) use (&$calls): void {
            $calls[] = ['sql' => $sql, 'params' => $params];
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'data' => [
                ['insert' => ['table' => 'settings', 'row' => ['key_name' => 'app.version', 'value' => '1.0'], 'only_if_not_exists' => ['key_name' => 'app.version']]],
            ],
        ], $addSql);

        self::assertCount(0, $calls);
    }

    public function testRunDataUpdateCallsAddSqlWithParams(): void
    {
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnArgument(0);

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('rowExists')->with('settings', ['key_name' => 'app.version'])->willReturn(true);
        $checker->method('getConnection')->willReturn($connection);

        $calls = [];
        $addSql = function (string $sql, array $params = []) use (&$calls): void {
            $calls[] = ['sql' => $sql, 'params' => $params];
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'data' => [
                ['update' => ['table' => 'settings', 'set' => ['value' => '1.1'], 'where' => ['key_name' => 'app.version'], 'only_if_exists' => true]],
            ],
        ], $addSql);

        self::assertCount(1, $calls);
        self::assertStringContainsString('UPDATE settings SET', $calls[0]['sql']);
        self::assertStringContainsString('WHERE key_name', $calls[0]['sql']);
        self::assertSame(['1.1', 'app.version'], $calls[0]['params']);
    }

    public function testRunIndexesCallsAddSqlWhenIndexDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('indexExists')->with('users', 'idx_email')->willReturn(false);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'indexes' => [
                ['table' => 'users', 'index_name' => 'idx_email', 'add_sql' => 'CREATE INDEX idx_email ON users (email)'],
            ],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('CREATE INDEX idx_email ON users (email)', $sqls[0]);
    }

    public function testRunModifyColumnsCallsAddSqlWhenColumnExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('users', 'email')->willReturn(true);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'modify_columns' => [
                ['table' => 'users', 'column' => 'email', 'modify_sql' => 'ALTER TABLE users MODIFY email VARCHAR(255) DEFAULT NULL'],
            ],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('ALTER TABLE users MODIFY email VARCHAR(255) DEFAULT NULL', $sqls[0]);
    }

    public function testRunModifyColumnsSkipsWhenColumnDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('users', 'email')->willReturn(false);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'modify_columns' => [
                ['table' => 'users', 'column' => 'email', 'modify_sql' => 'ALTER TABLE users MODIFY email VARCHAR(255)'],
            ],
        ], $addSql);

        self::assertCount(0, $sqls);
    }

    public function testRunDropColumnsCallsAddSqlWhenColumnExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('users', 'aka')->willReturn(true);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'drop_columns' => [
                ['table' => 'users', 'column' => 'aka', 'drop_sql' => 'ALTER TABLE users DROP COLUMN aka'],
            ],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('ALTER TABLE users DROP COLUMN aka', $sqls[0]);
    }

    public function testRunRenameColumnsCallsAddSqlWhenOldColumnExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('files', 'bucket')->willReturn(true);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'rename_columns' => [
                ['table' => 'files', 'old_name' => 'bucket', 'new_name' => 's3_bucket', 'rename_sql' => 'ALTER TABLE files CHANGE bucket s3_bucket VARCHAR(255)'],
            ],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('ALTER TABLE files CHANGE bucket s3_bucket VARCHAR(255)', $sqls[0]);
    }

    public function testRunDropIndexesCallsAddSqlWhenIndexExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('indexExists')->with('users', 'uniq_old')->willReturn(true);

        $sqls = [];
        $addSql = function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->run([
            'drop_indexes' => [
                ['table' => 'users', 'index_name' => 'uniq_old', 'drop_sql' => 'ALTER TABLE users DROP INDEX uniq_old'],
            ],
        ], $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('ALTER TABLE users DROP INDEX uniq_old', $sqls[0]);
    }

    public function testModifyColumnCallsAddSqlWhenColumnExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('users', 'email')->willReturn(true);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->modifyColumn('users', 'email', 'ALTER TABLE users MODIFY email VARCHAR(255)', $addSql);

        self::assertTrue($called);
    }

    public function testDropColumnCallsAddSqlWhenColumnExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('columnExists')->with('users', 'aka')->willReturn(true);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->dropColumn('users', 'aka', 'ALTER TABLE users DROP COLUMN aka', $addSql);

        self::assertTrue($called);
    }

    public function testDropIndexCallsAddSqlWhenIndexExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('indexExists')->with('users', 'idx_old')->willReturn(true);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->dropIndex('users', 'idx_old', 'ALTER TABLE users DROP INDEX idx_old', $addSql);

        self::assertTrue($called);
    }

    public function testEnsureForeignKeyCallsAddSqlWhenFkDoesNotExist(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('foreignKeyExists')->with('orders', 'fk_orders_user')->willReturn(false);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->ensureForeignKey('orders', 'fk_orders_user', 'ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)', $addSql);

        self::assertTrue($called);
    }

    public function testEnsureForeignKeyDoesNotCallAddSqlWhenFkExists(): void
    {
        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('foreignKeyExists')->with('orders', 'fk_orders_user')->willReturn(true);

        $called = false;
        $addSql = function (string $sql) use (&$called): void {
            $called = true;
        };

        $runner = new MigrationDefinitionRunner($checker);
        $runner->ensureForeignKey('orders', 'fk_orders_user', 'ALTER TABLE orders ADD CONSTRAINT fk_orders_user ...', $addSql);

        self::assertFalse($called);
    }
}
