<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Collation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use InvalidArgumentException;
use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Nowo\MigrationsKitBundle\Collation\CollationMismatch;
use Nowo\MigrationsKitBundle\Collation\CollationReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function str_contains;

#[CoversClass(CollationChecker::class)]
#[CoversClass(CollationReport::class)]
#[CoversClass(CollationMismatch::class)]
final class CollationCheckerTest extends TestCase
{
    /**
     * @param array<string, mixed> $params
     * @param list<array<string, mixed>> $tables
     * @param list<array<string, mixed>> $columns
     * @param array<string, mixed>|false $database
     */
    private function connection(AbstractPlatform $platform, array $params = [], array $tables = [], array $columns = [], array|false $database = false, int $tableCount = 0): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getParams')->willReturn($params);
        $connection->method('getDatabase')->willReturn('app');
        $connection->method('fetchAssociative')->willReturn($database);
        $connection->method('fetchOne')->willReturn((string) $tableCount);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static fn (string $sql): array => str_contains($sql, 'COLUMN') || str_contains($sql, 'column_name') ? $columns : $tables,
        );

        return $connection;
    }

    public function testMySqlTablesAndColumns(): void
    {
        $connection = $this->connection(
            new MySQLPlatform(),
            [],
            [
                ['table_name' => 'orders', 'collation_name' => 'utf8mb4_unicode_ci'],
                ['table_name' => 'tasks', 'collation_name' => 'utf8mb4_0900_ai_ci'],
                ['table_name' => 'uptime_result', 'collation_name' => 'utf8mb4_0900_ai_ci'],
            ],
            [
                ['table_name' => 'orders', 'column_name' => 'ref', 'collation_name' => 'UTF8MB4_UNICODE_CI'],
                ['table_name' => 'orders', 'column_name' => 'token', 'collation_name' => 'utf8mb4_bin'],
                ['table_name' => 'orders', 'column_name' => 'note', 'collation_name' => 'latin1_swedish_ci'],
                ['table_name' => 'uptime_result', 'column_name' => 'url', 'collation_name' => 'utf8mb4_0900_ai_ci'],
            ],
        );
        $checker = new CollationChecker('utf8mb4_unicode_ci', null, true, false, ['uptime_*'], ['orders.token']);
        $report  = $checker->check($connection);

        self::assertSame('mysql', $report->platform);
        self::assertTrue($report->supported);
        self::assertSame('utf8mb4', $report->expectedCharset);
        self::assertSame(3, $report->tablesChecked);
        self::assertSame(4, $report->columnsChecked);
        self::assertFalse($report->isOk());
        self::assertSame([
            'table tasks: collation utf8mb4_0900_ai_ci (expected utf8mb4_unicode_ci)',
            'column orders.note: collation latin1_swedish_ci (expected utf8mb4_unicode_ci)',
        ], array_map(static fn (CollationMismatch $m): string => $m->describe(), $report->failures()));
        self::assertCount(3, $report->ignored());
    }

    public function testRunOverridesAndExtraIgnoredTables(): void
    {
        $connection = $this->connection(new MariaDBPlatform(), [], [['table_name' => 'legacy', 'collation_name' => 'latin1_swedish_ci']]);
        $report     = (new CollationChecker('utf8mb4_unicode_ci', null, false))->check($connection, 'latin1_swedish_ci');

        self::assertSame('mariadb', $report->platform);
        self::assertTrue($report->isOk());
        self::assertSame(0, $report->columnsChecked);

        $report = (new CollationChecker('utf8mb4_unicode_ci', null, false))->check($connection, null, ['legacy']);
        self::assertTrue($report->isOk());
        self::assertCount(1, $report->ignored());
    }

    public function testExpectedValuesFromDoctrineDefaultTableOptions(): void
    {
        $tables = [['table_name' => 't', 'collation_name' => 'utf8mb4_unicode_ci']];
        $report = (new CollationChecker())->check($this->connection(new MySQLPlatform(), ['defaultTableOptions' => ['collation' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4']], $tables));
        self::assertTrue($report->isOk());
        self::assertSame('utf8mb4_unicode_ci', $report->expectedCollation);

        $report = (new CollationChecker())->check($this->connection(new MySQLPlatform(), ['defaultTableOptions' => ['collate' => 'utf8mb4_unicode_ci']], $tables));
        self::assertSame('utf8mb4_unicode_ci', $report->expectedCollation);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No expected collation');
        (new CollationChecker())->check($this->connection(new MySQLPlatform()));
    }

    public function testMySqlDatabaseDefault(): void
    {
        $connection = $this->connection(new MySQLPlatform(), [], [], [], ['charset_name' => 'latin1', 'collation_name' => 'latin1_swedish_ci']);
        $report     = (new CollationChecker('utf8mb4_unicode_ci', null, false, true))->check($connection);

        self::assertSame([
            'database app: collation latin1_swedish_ci (expected utf8mb4_unicode_ci)',
            'database app: charset latin1 (expected utf8mb4)',
        ], array_map(static fn (CollationMismatch $m): string => $m->describe(), $report->failures()));

        // No row (no current database): nothing to compare.
        $report = (new CollationChecker('utf8mb4_unicode_ci', null, false, true))->check($this->connection(new MySQLPlatform()));
        self::assertTrue($report->isOk());
    }

    public function testPostgreSqlDatabaseAndExplicitColumnCollations(): void
    {
        $connection = $this->connection(
            new PostgreSQLPlatform(),
            [],
            [],
            [
                ['table_name' => 'users', 'column_name' => 'email', 'collation_name' => 'C'],
                ['table_name' => 'users', 'column_name' => 'name', 'collation_name' => 'en_US.utf8'],
            ],
            ['charset_name' => 'SQL_ASCII', 'collation_name' => ''],
            7,
        );
        $report = (new CollationChecker('en_US.utf8', 'UTF8', true, true))->check($connection);

        self::assertSame('postgresql', $report->platform);
        self::assertSame(7, $report->tablesChecked);
        self::assertSame(2, $report->columnsChecked);
        self::assertSame([
            'database app: collation (none) (expected en_US.utf8)',
            'database app: charset SQL_ASCII (expected UTF8)',
            'column users.email: collation C (expected en_US.utf8)',
        ], array_map(static fn (CollationMismatch $m): string => $m->describe(), $report->failures()));

        $report = (new CollationChecker('en_US.utf8', null, false, true))->check($this->connection(new PostgreSQLPlatform(), [], [], [], ['charset_name' => 'UTF8', 'collation_name' => 'en_US.utf8']));
        self::assertTrue($report->isOk());
        self::assertNull($report->expectedCharset);
    }

    public function testOtherPlatformsAreNotSupported(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $report   = (new CollationChecker())->check($this->connection($platform));

        self::assertFalse($report->supported);
        self::assertSame('', $report->expectedCollation);
        self::assertTrue($report->isOk());
    }
}
