<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Nowo\MigrationsKitBundle\Migration\MigrationDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaCheckerInterface;
use PHPUnit\Framework\TestCase;

class MigrationDefinitionTest extends TestCase
{
    public function testFromArrayBuildsDefinition(): void
    {
        $def = MigrationDefinition::fromArray([
            'tables'  => ['users' => ['create_sql' => 'CREATE TABLE users (id INT)']],
            'columns' => [['table' => 'users', 'column' => 'email', 'add_sql' => 'ALTER TABLE users ADD email VARCHAR(180)']],
        ]);

        self::assertSame(['users' => ['create_sql' => 'CREATE TABLE users (id INT)']], $def->tables);
        self::assertCount(1, $def->columns);
        self::assertSame([], $def->indexes);
    }

    public function testFromArrayWithEmptyDefinition(): void
    {
        $def = MigrationDefinition::fromArray([]);

        self::assertSame([], $def->tables);
        self::assertSame([], $def->columns);
        self::assertSame([], $def->data);
    }

    public function testToArrayExportsOnlyNonEmptySections(): void
    {
        $def = new MigrationDefinition(
            tables: ['t' => ['create_sql' => 'CREATE TABLE t (id INT)']],
            columns: [],
            indexes: [['table' => 't', 'index_name' => 'i', 'add_sql' => 'CREATE INDEX i ON t (c)']],
        );

        $arr = $def->toArray();

        self::assertArrayHasKey('tables', $arr);
        self::assertArrayHasKey('indexes', $arr);
        self::assertArrayNotHasKey('columns', $arr);
    }

    public function testRunDelegatesToRunner(): void
    {
        $sqls   = [];
        $addSql = static function (string $sql) use (&$sqls): void {
            $sqls[] = $sql;
        };

        $checker = $this->createMock(SchemaCheckerInterface::class);
        $checker->method('tableExists')->willReturn(false);
        $runner = new MigrationDefinitionRunner($checker);

        $def = MigrationDefinition::fromArray([
            'tables' => ['users' => ['create_sql' => 'CREATE TABLE users (id INT)']],
        ]);
        $def->run($runner, $addSql);

        self::assertCount(1, $sqls);
        self::assertSame('CREATE TABLE users (id INT)', $sqls[0]);
    }

    public function testToArrayExportsAllSectionsWhenNonEmpty(): void
    {
        $def = new MigrationDefinition(
            tables: ['t' => ['create_sql' => 'CREATE TABLE t (id INT)']],
            columns: [['table' => 't', 'column' => 'c', 'add_sql' => 'ALTER TABLE t ADD c INT']],
            indexes: [['table' => 't', 'index_name' => 'i', 'add_sql' => 'CREATE INDEX i ON t (c)']],
            renameColumns: [['table' => 't', 'old_name' => 'a', 'new_name' => 'b', 'rename_sql' => 'CHANGE a b INT']],
            modifyColumns: [['table' => 't', 'column' => 'c', 'modify_sql' => 'MODIFY c VARCHAR(10)']],
            dropIndexes: [['table' => 't', 'index_name' => 'i', 'drop_sql' => 'DROP INDEX i']],
            dropColumns: [['table' => 't', 'column' => 'x', 'drop_sql' => 'DROP x']],
            data: [['insert' => ['table' => 't', 'row' => ['k' => 'v']]]],
        );

        $arr = $def->toArray();

        self::assertArrayHasKey('tables', $arr);
        self::assertArrayHasKey('columns', $arr);
        self::assertArrayHasKey('indexes', $arr);
        self::assertArrayHasKey('rename_columns', $arr);
        self::assertArrayHasKey('modify_columns', $arr);
        self::assertArrayHasKey('drop_indexes', $arr);
        self::assertArrayHasKey('drop_columns', $arr);
        self::assertArrayHasKey('data', $arr);
    }
}
