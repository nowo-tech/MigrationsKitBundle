<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Persistence\ConnectionRegistry;
use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Nowo\MigrationsKitBundle\Command\CheckCollationsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function str_contains;

#[CoversClass(CheckCollationsCommand::class)]
final class CheckCollationsCommandTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $tables
     * @param list<array<string, mixed>> $columns
     */
    private function mysql(array $tables, array $columns = []): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('getParams')->willReturn([]);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static fn (string $sql): array => str_contains($sql, 'COLUMN_NAME') ? $columns : $tables,
        );

        return $connection;
    }

    private function tester(object $connection, ?string &$requested = null, ?CollationChecker $checker = null): CommandTester
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturnCallback(static function (?string $name) use ($connection, &$requested): object {
            $requested = $name;

            return $connection;
        });

        return new CommandTester(new CheckCollationsCommand($registry, $checker ?? new CollationChecker('utf8mb4_unicode_ci', null, true, false, ['uptime_*']), 'default'));
    }

    public function testPassesAndCountsIgnoredObjects(): void
    {
        $tester = $this->tester($this->mysql(
            [['table_name' => 'a', 'collation_name' => 'utf8mb4_unicode_ci'], ['table_name' => 'uptime_x', 'collation_name' => 'utf8mb4_0900_ai_ci']],
            [['table_name' => 'a', 'column_name' => 'name', 'collation_name' => 'utf8mb4_unicode_ci']],
        ), $requested);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('default', $requested);
        self::assertStringContainsString('OK: collations (utf8mb4_unicode_ci on mysql; 2 table(s), 1 column(s) checked; 1 ignored object(s) still to convert).', $tester->getDisplay());

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString('ignored table uptime_x: collation utf8mb4_0900_ai_ci', $tester->getDisplay());
    }

    public function testFailsListingEveryObject(): void
    {
        $tester = $this->tester($this->mysql([['table_name' => 'tasks', 'collation_name' => 'utf8mb4_0900_ai_ci']]), $requested);

        self::assertSame(Command::FAILURE, $tester->execute(['--connection' => 'legacy']));
        self::assertSame('legacy', $requested);
        $display = $tester->getDisplay();
        self::assertStringContainsString('table tasks: collation utf8mb4_0900_ai_ci (expected utf8mb4_unicode_ci)', $display);
        self::assertStringContainsString('FAIL: 1 object(s) not in utf8mb4_unicode_ci on mysql.', $display);
        self::assertStringContainsString('DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $display);

        self::assertSame(Command::SUCCESS, $tester->execute(['--ignore-table' => ['tasks']]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--expected' => 'utf8mb4_0900_ai_ci']));
    }

    public function testSkipsPlatformsWithoutCollations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));
        $connection->method('getParams')->willReturn([]);
        $tester = $this->tester($connection);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('SKIP: no collation check for', $tester->getDisplay());
    }

    public function testMisconfigurationExitsWithTwo(): void
    {
        $tester = $this->tester($this->mysql([]), $requested, new CollationChecker());
        self::assertSame(CheckCollationsCommand::EXIT_MISCONFIGURED, $tester->execute([]));
        self::assertStringContainsString('No expected collation', $tester->getDisplay());

        $tester = $this->tester(new stdClass());
        self::assertSame(CheckCollationsCommand::EXIT_MISCONFIGURED, $tester->execute(['--connection' => 'odd']));
        self::assertStringContainsString('Connection "odd" is not a DBAL connection.', $tester->getDisplay());

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willThrowException(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused <x>'));
        self::assertSame(CheckCollationsCommand::EXIT_MISCONFIGURED, $this->tester($connection)->execute([]));
    }
}
