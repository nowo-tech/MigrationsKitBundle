<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Collation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Nowo\MigrationsKitBundle\Collation\CollationFoldingProbe;
use Nowo\MigrationsKitBundle\Collation\ExactCollationSupport;
use Nowo\MigrationsKitBundle\Collation\TableCollationReader;
use Nowo\MigrationsKitBundle\Collation\UniformCollation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * The one place where information_schema.TABLES.TABLE_COLLATION is selected, and the one spelling of the remedy.
 */
#[CoversClass(TableCollationReader::class)]
#[CoversClass(UniformCollation::class)]
#[CoversClass(CollationFoldingProbe::class)]
final class TableCollationReaderTest extends TestCase
{
    public function testNoTableNamesMeansNoQueryAtAll(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAllAssociative');

        self::assertSame([], TableCollationReader::mysql($connection, []));
    }

    public function testEveryBaseTableIsReadWhenNoNamesAreGiven(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(static function (string $sql, array $params = []): array {
            self::assertStringContainsString('TABLE_COLLATION', $sql);
            self::assertStringNotContainsString('LOWER(TABLE_NAME) IN', $sql);
            self::assertSame([], $params);

            return [['table_name' => 'orders', 'collation_name' => 'utf8mb4_unicode_ci'], ['table_name' => 'tasks', 'collation_name' => null]];
        });

        self::assertSame(
            [
                ['table_name' => 'orders', 'collation_name' => 'utf8mb4_unicode_ci'],
                // A table the catalogue has no collation for becomes "", never a silent pass.
                ['table_name' => 'tasks', 'collation_name' => ''],
            ],
            TableCollationReader::mysql($connection),
        );
    }

    public function testOnlyTheNamedTablesAreReadWhenNamesAreGiven(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(static function (string $sql, array $params = []): array {
            self::assertStringContainsString('LOWER(TABLE_NAME) IN (?)', $sql);
            self::assertSame([['audit_entry']], $params);

            return [['table_name' => 'audit_entry', 'collation_name' => 'utf8mb4_unicode_ci']];
        });

        self::assertSame([['table_name' => 'audit_entry', 'collation_name' => 'utf8mb4_unicode_ci']], TableCollationReader::mysql($connection, ['audit_entry']));
    }

    public function testTheRemedyChangesTheDefaultAndNeverConvertsTheTable(): void
    {
        // CONVERT TO would rewrite every text column of the table, the declared binary keys included.
        $alter = TableCollationReader::alter('audit_entry', 'utf8mb4', 'utf8mb4_unicode_ci');

        self::assertSame('ALTER TABLE `audit_entry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;', $alter);
        self::assertStringNotContainsString('CONVERT TO', $alter);
    }

    public function testACatalogueThatCannotBeReadAtAllRaisesInsteadOfAnswering(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('fetchAssociative')->willThrowException(new RuntimeException('SELECT command denied for table COLLATIONS'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read information_schema.COLLATIONS for "utf8mb4_unicode_ci"');

        CollationFoldingProbe::folds($connection, 'utf8mb4_unicode_ci', ExactCollationSupport::of(['utf8mb4_0900_bin']));
    }

    public function testTheProbeAsksTheServerWithTheCollationCharacterSet(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('fetchAssociative')->willReturn(['collation_name' => 'utf8mb4_unicode_ci', 'charset_name' => 'utf8mb4']);
        $connection->method('fetchOne')->willReturnCallback(static function (string $sql): int {
            self::assertTrue(str_contains($sql, "CONVERT('a' USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT('A' USING utf8mb4)"), $sql);

            return 1;
        });

        self::assertTrue(CollationFoldingProbe::folds($connection, 'utf8mb4_unicode_ci', ExactCollationSupport::of(['utf8mb4_0900_bin'])));
    }

    public function testTheBinaryCharsetCollationIsNeverWrittenIntoAProbe(): void
    {
        // COLLATE binary is a syntax error on MySQL, and a binary collation cannot fold anything anyway.
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAssociative');

        self::assertFalse(CollationFoldingProbe::folds($connection, 'binary', ExactCollationSupport::of(['utf8mb4_0900_bin'])));
    }

    public function testACaseSensitiveNonBinaryCollationWithAnAiNameStillCountsAsFolding(): void
    {
        // The engine answers first; the name only ever widens that answer, never narrows it.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['collation_name' => 'utf8mb4_0900_ai_cs', 'charset_name' => 'utf8mb4']);
        $connection->method('fetchOne')->willReturn(0);

        self::assertTrue(CollationFoldingProbe::folds($connection, 'utf8mb4_0900_ai_cs', ExactCollationSupport::of(['utf8mb4_0900_bin'])));
    }
}
