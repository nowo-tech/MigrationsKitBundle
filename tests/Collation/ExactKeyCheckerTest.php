<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Collation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\MigrationsKitBundle\Collation\ExactCollationSupport;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyFinding;
use Nowo\MigrationsKitBundle\Collation\ExactKeyReport;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumns;
use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

#[CoversClass(ExactKeyChecker::class)]
#[CoversClass(ExactCollationSupport::class)]
#[CoversClass(ExactKeyColumn::class)]
#[CoversClass(ExactKeyColumns::class)]
#[CoversClass(ExactKeyFinding::class)]
#[CoversClass(ExactKeyReport::class)]
#[CoversClass(ExactKeyStatus::class)]
final class ExactKeyCheckerTest extends TestCase
{
    /**
     * @param list<string> $collations binary NO PAD collations the fake server offers
     * @param list<string> $tables base tables that exist
     * @param list<array<string, mixed>> $columns information_schema.COLUMNS rows
     */
    private function connection(AbstractPlatform $platform, array $collations = ['utf8mb4_0900_bin'], array $tables = [], array $columns = []): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static fn (string $sql): array => str_contains($sql, 'COLLATIONS') ? $collations : $tables,
        );
        $connection->method('fetchAllAssociative')->willReturn($columns);

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function column(string $table, string $column, ?string $collation, string $type = 'varchar(64)'): array
    {
        return ['table_name' => $table, 'column_name' => $column, 'collation_name' => $collation, 'column_type' => $type, 'is_nullable' => 'NO'];
    }

    public function testPassesOnNoPadBinaryAndFailsOnTheServerDefault(): void
    {
        $connection = $this->connection(
            new MySQLPlatform(),
            ['utf8mb4_0900_bin'],
            ['audit_entry'],
            [
                $this->column('audit_entry', 'actor_id', 'utf8mb4_0900_bin'),
                // The case the positive list exists for: nobody ever wrote a COLLATE, so it took the server default.
                $this->column('audit_entry', 'subject_id', 'utf8mb4_0900_ai_ci'),
            ],
        );
        $checker = new ExactKeyChecker([new ExactKeyColumns(
            new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle', 'who acted'),
            new ExactKeyColumn('audit_entry', 'subject_id', 'nowo-tech/audit-kit-bundle', 'what was acted on'),
        )]);

        $report = $checker->check($connection);

        self::assertTrue($report->supported);
        self::assertSame('mysql', $report->platform);
        self::assertSame('utf8mb4_0900_bin', $report->expectedCollation);
        self::assertFalse($report->degraded);
        self::assertSame(1, $report->declaringBundles);
        self::assertCount(1, $report->withStatus(ExactKeyStatus::OK));

        $failures = $report->failures();
        self::assertCount(1, $failures);
        self::assertFalse($report->isOk());
        self::assertSame('audit_entry.subject_id', $failures[0]->column->describe());
        self::assertSame(ExactKeyStatus::FAIL, $failures[0]->status);
        self::assertSame('utf8mb4_0900_ai_ci', $failures[0]->actual);
        self::assertSame('ALTER TABLE `audit_entry` MODIFY `subject_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL;', $failures[0]->alter);
    }

    public function testUtf8mb4BinFailsWhenTheServerOffersNoPad(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['tracking'], [$this->column('tracking', 'recipient_id', 'utf8mb4_bin')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('tracking', 'recipient_id', 'mailkit'))]))->check($connection);

        self::assertCount(1, $report->failures());
        self::assertSame(ExactKeyStatus::FAIL, $report->failures()[0]->status);
    }

    public function testUtf8mb4BinIsTheAcceptedFallbackOnAServerWithoutNoPad(): void
    {
        // MySQL 5.7 and MySQL 8.0.0-8.0.16: information_schema.COLLATIONS offers no NO PAD collation at all.
        $connection = $this->connection(new MySQLPlatform(), [], ['tracking'], [$this->column('tracking', 'recipient_id', 'utf8mb4_bin')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('tracking', 'recipient_id', 'mailkit'))]))->check($connection);

        self::assertTrue($report->degraded);
        self::assertSame('utf8mb4_bin', $report->expectedCollation);
        self::assertTrue($report->isOk());
        self::assertCount(1, $report->withStatus(ExactKeyStatus::FALLBACK));
    }

    public function testMariaDbNoPadCollationIsAccepted(): void
    {
        $connection = $this->connection(new MariaDBPlatform(), ['utf8mb4_nopad_bin'], ['vault_grants'], [$this->column('vault_grants', 'grantee_id', 'utf8mb4_nopad_bin')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('vault_grants', 'grantee_id', 'vault'))]))->check($connection);

        self::assertSame('mariadb', $report->platform);
        self::assertSame('utf8mb4_nopad_bin', $report->expectedCollation);
        self::assertTrue($report->isOk());
    }

    public function testAnInactiveColumnIsSkippedButAMissingActiveOneFails(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], [], []);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(
            new ExactKeyColumn('chat_user_reference', 'user_key', 'chatkit', 'user key', 'nowo_chat_kit.user_reference: string', false),
            new ExactKeyColumn('chat_conversation', 'external_id', 'chatkit', 'conversation id'),
        )]))->check($connection);

        $skipped = $report->withStatus(ExactKeyStatus::SKIPPED);
        self::assertCount(1, $skipped);
        self::assertSame('nowo_chat_kit.user_reference: string', $skipped[0]->detail);

        $failures = $report->failures();
        self::assertCount(1, $failures);
        self::assertSame(ExactKeyStatus::MISSING, $failures[0]->status);
        self::assertStringContainsString('table not in the schema', $failures[0]->detail);
    }

    public function testAColumnMissingFromAnExistingTableIsReportedAsARenameOrDrop(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['chat_conversation'], [$this->column('chat_conversation', 'id', null, 'int')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('chat_conversation', 'external_id', 'chatkit'))]))->check($connection);

        self::assertSame(ExactKeyStatus::MISSING, $report->failures()[0]->status);
        self::assertStringContainsString('column does not', $report->failures()[0]->detail);
    }

    public function testAFindingDescribesItselfWithStatusCollationAndContext(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['api_token'], [$this->column('api_token', 'token_hash', 'utf8mb4_bin')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token', 'bearer token hash'))]))->check($connection);

        // One line per column, the status left-padded so a long report lines up, then what was found and why.
        self::assertSame('fail     api_token.token_hash: utf8mb4_bin (bearer token hash)', $report->failures()[0]->describe());
        self::assertSame('ok       t.c', (new ExactKeyFinding(new ExactKeyColumn('t', 'c', 'o'), ExactKeyStatus::OK))->describe());
    }

    public function testAnIgnoredColumnThatIsAbsentIsReportedAsBothRatherThanFailed(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], [], []);
        $report     = (new ExactKeyChecker(
            [new ExactKeyColumns(new ExactKeyColumn('legal_document', 'slug', 'legalkit'))],
            ['legal_*.slug' => 'moved to the host table'],
        ))->check($connection);

        self::assertTrue($report->isOk(), 'A column the host deliberately moved elsewhere may legitimately be absent.');
        $ignored = $report->withStatus(ExactKeyStatus::IGNORED);
        self::assertCount(1, $ignored);
        self::assertSame('', $ignored[0]->actual);
        self::assertStringContainsString('ignored by host: moved to the host table; also table not in the schema', $ignored[0]->detail);
    }

    public function testHostIgnoreKeepsTheColumnVisibleInsteadOfHidingIt(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['legal_document'], [$this->column('legal_document', 'slug', 'utf8mb4_unicode_ci')]);
        $report     = (new ExactKeyChecker(
            [new ExactKeyColumns(new ExactKeyColumn('legal_document', 'slug', 'legalkit'))],
            ['legal_document.slug' => 'legacy slugs are compared case-insensitively on purpose'],
        ))->check($connection);

        self::assertTrue($report->isOk());
        $ignored = $report->withStatus(ExactKeyStatus::IGNORED);
        self::assertCount(1, $ignored);
        self::assertSame('utf8mb4_unicode_ci', $ignored[0]->actual);
        self::assertStringContainsString('ignored by host: legacy slugs', $ignored[0]->detail);
    }

    public function testABinaryTypeWithoutACollationIsExact(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['sessions'], [$this->column('sessions', 'token', null, 'varbinary(64)')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('sessions', 'token', 'userkit'))]))->check($connection);

        self::assertTrue($report->isOk());
        self::assertSame('(none)', $report->withStatus(ExactKeyStatus::OK)[0]->actual);
    }

    public function testTableNamesAreMatchedCaseInsensitively(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['Wf_Instance'], [$this->column('Wf_Instance', 'Owner_Id', 'utf8mb4_0900_bin')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('wf_instance', 'owner_id', 'workflow'))]))->check($connection);

        self::assertTrue($report->isOk());
    }

    public function testPostgreSqlIsSkippedCleanly(): void
    {
        $report = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('t', 'c', 'o'))]))->check($this->connection(new PostgreSQLPlatform()));

        self::assertFalse($report->supported);
        self::assertTrue($report->isOk());
        self::assertSame([], $report->findings);
    }

    public function testAServerWithoutPadAttributeIsAskedByCollationNameInstead(): void
    {
        // MySQL 5.7 has no PAD_ATTRIBUTE column and MariaDB may not expose it: the first probe errors, the second
        // answers by name. The error must not be mistaken for "this server offers no NO PAD collation".
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MariaDBPlatform());
        $connection->method('fetchFirstColumn')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'PAD_ATTRIBUTE')) {
                throw new RuntimeException("Unknown column 'PAD_ATTRIBUTE' in 'where clause'");
            }

            return str_contains($sql, 'COLLATIONS') ? ['utf8mb4_nopad_bin'] : ['vault_grant'];
        });
        $connection->method('fetchAllAssociative')->willReturn([$this->column('vault_grant', 'grantee_id', 'utf8mb4_nopad_bin')]);

        $report = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('vault_grant', 'grantee_id', 'vault'))]))->check($connection);

        self::assertFalse($report->degraded, 'The server does offer a NO PAD collation; only the first probe failed.');
        self::assertSame('utf8mb4_nopad_bin', $report->expectedCollation);
        self::assertTrue($report->isOk());
    }

    public function testAnUnreadableCollationCatalogueRaisesInsteadOfAcceptingPadSpace(): void
    {
        // Degradation is a claim about the server, and it has to be proven. If no probe can be read (no grant on
        // information_schema.COLLATIONS, an unreadable catalogue), assuming "no NO PAD collation here" would accept
        // utf8mb4_bin - binary but PAD SPACE - on a server that offers utf8mb4_0900_bin, and the gate would pass over
        // identity columns whose keys still collide. The check must refuse to answer instead.
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('fetchFirstColumn')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'COLLATIONS')) {
                throw new RuntimeException('SELECT command denied to user \'ci\'@\'%\' for table \'COLLATIONS\'');
            }

            return ['api_token'];
        });
        $connection->method('fetchAllAssociative')->willReturn([$this->column('api_token', 'token_hash', 'utf8mb4_bin')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read information_schema.COLLATIONS');

        (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);
    }

    public function testNoProvidersMeansNothingDeclaredAndNoFailure(): void
    {
        $report = (new ExactKeyChecker())->check($this->connection(new MySQLPlatform()));

        self::assertTrue($report->isOk());
        self::assertSame(0, $report->declaringBundles);
    }
}
