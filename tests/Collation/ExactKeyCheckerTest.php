<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Collation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\MigrationsKitBundle\Collation\CollationFoldingProbe;
use Nowo\MigrationsKitBundle\Collation\ExactCollationSupport;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyFinding;
use Nowo\MigrationsKitBundle\Collation\ExactKeyReport;
use Nowo\MigrationsKitBundle\Collation\ExactKeyTableFinding;
use Nowo\MigrationsKitBundle\Collation\TableCollationReader;
use Nowo\MigrationsKitBundle\Collation\UniformCollation;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumns;
use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function is_int;
use function str_contains;

#[CoversClass(ExactKeyChecker::class)]
#[CoversClass(ExactCollationSupport::class)]
#[CoversClass(ExactKeyColumn::class)]
#[CoversClass(ExactKeyColumns::class)]
#[CoversClass(ExactKeyFinding::class)]
#[CoversClass(ExactKeyReport::class)]
#[CoversClass(ExactKeyTableFinding::class)]
#[CoversClass(CollationFoldingProbe::class)]
#[CoversClass(TableCollationReader::class)]
#[CoversClass(UniformCollation::class)]
#[CoversClass(ExactKeyStatus::class)]
final class ExactKeyCheckerTest extends TestCase
{
    /**
     * A fake information_schema. Since 2.9 the check also reads information_schema.TABLES.TABLE_COLLATION, so a table
     * is given either as a bare name (it then has $tableCollation as its own default) or as "name => own default".
     *
     * @param list<string> $collations binary NO PAD collations the fake server offers
     * @param array<string, string>|list<string> $tables base tables that exist
     * @param list<array<string, mixed>> $columns information_schema.COLUMNS rows
     * @param array<string, mixed> $params connection parameters; defaultTableOptions carries the host's uniform
     *                                     collation, exactly as the sibling gate reads it
     * @param bool|null $folds what the server answers when asked whether the table default folds case; null = no
     *                         answer at all, which must never be read as "it does not fold"
     */
    private function connection(AbstractPlatform $platform, array $collations = ['utf8mb4_0900_bin'], array $tables = [], array $columns = [], string $tableCollation = 'utf8mb4_unicode_ci', array $params = ['defaultTableOptions' => ['collation' => 'utf8mb4_unicode_ci']], ?bool $folds = null): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getParams')->willReturn($params);
        $connection->method('fetchFirstColumn')->willReturn($collations);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            fn (string $sql): array => str_contains($sql, 'information_schema.TABLES') ? $this->tableRows($tables, $tableCollation) : $columns,
        );
        $connection->method('fetchAssociative')->willReturn(['collation_name' => 'utf8mb4_site_local', 'charset_name' => 'utf8mb4']);
        $connection->method('fetchOne')->willReturn($folds === null ? false : (int) $folds);

        return $connection;
    }

    /**
     * The SQLite platform, built from a real connection: DBAL 3 spells the class SqlitePlatform and DBAL 4
     * SQLitePlatform, so neither name can be written here.
     */
    private function sqlitePlatform(): AbstractPlatform
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->getDatabasePlatform();
    }

    /**
     * @param array<string, string>|list<string> $tables
     *
     * @return list<array<string, string>>
     */
    private function tableRows(array $tables, string $tableCollation): array
    {
        $rows = [];
        foreach ($tables as $name => $collation) {
            $rows[] = is_int($name)
                ? ['table_name' => $collation, 'collation_name' => $tableCollation]
                : ['table_name' => $name, 'collation_name' => $collation];
        }

        return $rows;
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
        $connection->method('getParams')->willReturn(['defaultTableOptions' => ['collation' => 'utf8mb4_unicode_ci']]);
        $connection->method('fetchFirstColumn')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'PAD_ATTRIBUTE')) {
                throw new RuntimeException("Unknown column 'PAD_ATTRIBUTE' in 'where clause'");
            }

            return ['utf8mb4_nopad_bin'];
        });
        $connection->method('fetchAllAssociative')->willReturnCallback(
            fn (string $sql): array => str_contains($sql, 'information_schema.TABLES')
                ? $this->tableRows(['vault_grant'], 'utf8mb4_unicode_ci')
                : [$this->column('vault_grant', 'grantee_id', 'utf8mb4_nopad_bin')],
        );

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
            throw new RuntimeException('SELECT command denied to user \'ci\'@\'%\' for table \'COLLATIONS\'');
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

    public function testATableWhoseColumnsAreAllCorrectButWhoseOwnDefaultIsInsensitiveFails(): void
    {
        // The measured blind spot: on a real host this check said "68 ok, 0 fallback" over a database whose table
        // default was wrong, and only the generic uniformity gate caught it. The declared columns are binary NO PAD;
        // the table they live on is not, so the next text column added here without a COLLATE inherits the folding
        // default and either breaks the join (MySQL 1267) or silently folds what REQ-SEC-006 keeps apart.
        $connection = $this->connection(
            new MySQLPlatform(),
            ['utf8mb4_0900_bin'],
            ['audit_entry' => 'utf8mb4_0900_ai_ci'],
            [
                $this->column('audit_entry', 'actor_id', 'utf8mb4_0900_bin'),
                $this->column('audit_entry', 'subject_id', 'utf8mb4_0900_bin'),
            ],
        );
        $checker = new ExactKeyChecker([new ExactKeyColumns(
            new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle'),
            new ExactKeyColumn('audit_entry', 'subject_id', 'nowo-tech/audit-kit-bundle'),
        )]);

        $report = $checker->check($connection);

        self::assertSame([], $report->failures(), 'Every declared column is binary NO PAD: the columns are not the problem.');
        self::assertCount(2, $report->withStatus(ExactKeyStatus::OK));
        self::assertFalse($report->isOk(), 'A table set up to fail later is not a pass.');

        $tableFailures = $report->tableFailures();
        self::assertCount(1, $tableFailures);
        self::assertSame('audit_entry', $tableFailures[0]->table);
        self::assertSame(ExactKeyStatus::FAIL, $tableFailures[0]->status);
        self::assertSame('utf8mb4_0900_ai_ci', $tableFailures[0]->actual);
        self::assertStringContainsString('without an explicit COLLATE', $tableFailures[0]->detail);
        self::assertStringContainsString('1267', $tableFailures[0]->detail);

        // The remedy names the host's uniform collation and changes the default only.
        self::assertSame('ALTER TABLE `audit_entry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;', $tableFailures[0]->alter);
        self::assertStringNotContainsString('CONVERT TO', $tableFailures[0]->alter);
        self::assertStringNotContainsString('_bin', $tableFailures[0]->alter, 'The binary collation belongs on the key columns, never on a table default.');
    }

    public function testTheSameTableWithTheHostUniformDefaultPasses(): void
    {
        $connection = $this->connection(
            new MySQLPlatform(),
            ['utf8mb4_0900_bin'],
            ['audit_entry' => 'UTF8MB4_UNICODE_CI'],
            [$this->column('audit_entry', 'actor_id', 'utf8mb4_0900_bin')],
        );
        $report = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('audit_entry', 'actor_id', 'auditkit'))]))->check($connection);

        self::assertTrue($report->isOk());
        self::assertSame([], $report->tableFailures());
        self::assertCount(1, $report->tablesWithStatus(ExactKeyStatus::OK));
        self::assertSame('ok       audit_entry (table default): UTF8MB4_UNICODE_CI (table default, as configured for this host)', $report->tableFindings[0]->describe());
    }

    public function testTheConfiguredUniformCollationWinsOverDoctrineDefaultTableOptions(): void
    {
        $connection = $this->connection(
            new MySQLPlatform(),
            ['utf8mb4_0900_bin'],
            ['audit_entry' => 'utf8mb4_unicode_ci'],
            [$this->column('audit_entry', 'actor_id', 'utf8mb4_0900_bin')],
        );
        $report = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('audit_entry', 'actor_id', 'auditkit'))], [], 'utf8mb4_general_ci'))->check($connection);

        self::assertFalse($report->isOk());
        self::assertSame('ALTER TABLE `audit_entry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;', $report->tableFailures()[0]->alter);
    }

    public function testWithoutAnyUniformCollationConfiguredTheServerDecidesWhetherTheDefaultFolds(): void
    {
        // No expected value anywhere, so there is nothing to compare with - and silence is not a pass. What is asked
        // instead is the only thing that matters: does this collation fold two values that differ only in case? The
        // name "utf8mb4_site_local" says nothing either way, which is the point: the engine answers, not the string.
        $folding = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['api_token' => 'utf8mb4_site_local'], [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')], 'utf8mb4_site_local', [], true);
        $report  = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($folding);

        self::assertFalse($report->isOk());
        $failure = $report->tableFailures()[0];
        self::assertStringContainsString('folds case and accents', $failure->detail);
        self::assertStringContainsString('default_table_options.collation', $failure->detail);
        self::assertStringContainsString('ALTER TABLE `api_token` DEFAULT CHARACTER SET', $failure->detail);
        self::assertStringContainsString('never CONVERT TO', $failure->detail);
        self::assertNull($failure->alter, 'No collation the host never declared is ever put in a runnable statement.');

        $sensitive = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['api_token' => 'utf8mb4_site_local'], [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')], 'utf8mb4_site_local', [], false);
        $ok        = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($sensitive);

        self::assertTrue($ok->isOk());
        self::assertStringContainsString('asked of the server', $ok->tableFindings[0]->detail);
    }

    public function testABinaryTableDefaultIsNeverProbedAndNeverFails(): void
    {
        // COLLATE binary is a syntax error on MySQL and a binary collation cannot fold anything, so the probe must
        // not be run for one. $folds is null here: any attempt to ask would read as "no answer" and raise.
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['api_token' => 'utf8mb4_0900_bin'], [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')], 'utf8mb4_0900_bin', []);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);

        self::assertTrue($report->isOk());
        self::assertCount(1, $report->tablesWithStatus(ExactKeyStatus::OK));
    }

    public function testATableCollationThatCannotBeReadIsAFailureAndNeverAPass(): void
    {
        // The table is there and its own default is not readable (no grant, an engine that does not report it). The
        // one direction a gate must never fail in is "nothing to worry about".
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['api_token' => ''], [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')]);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);

        self::assertFalse($report->isOk());
        self::assertSame([], $report->failures());
        $failure = $report->tableFailures()[0];
        self::assertSame('', $failure->actual);
        self::assertStringContainsString('TABLE_COLLATION is empty', $failure->detail);
        self::assertNull($failure->alter);
        self::assertSame('fail     api_token (table default) (information_schema.TABLES.TABLE_COLLATION is empty for this table: its own default cannot be read, so no text column added to it later can be vouched for. Grant SELECT on information_schema to the connection used by the check.)', $failure->describe());
    }

    public function testACollationTheServerDoesNotKnowRaisesInsteadOfPassing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('getParams')->willReturn([]);
        $connection->method('fetchFirstColumn')->willReturn(['utf8mb4_0900_bin']);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            fn (string $sql): array => str_contains($sql, 'information_schema.TABLES')
                ? $this->tableRows(['api_token' => 'utf8mb4_site_local'], 'x')
                : [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')],
        );
        $connection->method('fetchAssociative')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not in information_schema.COLLATIONS on this server');

        (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);
    }

    public function testAProbeThatCannotBeRunRaisesInsteadOfPassing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('getParams')->willReturn([]);
        $connection->method('fetchFirstColumn')->willReturn(['utf8mb4_0900_bin']);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            fn (string $sql): array => str_contains($sql, 'information_schema.TABLES')
                ? $this->tableRows(['api_token' => 'utf8mb4_site_local'], 'x')
                : [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')],
        );
        $connection->method('fetchAssociative')->willReturn(['collation_name' => 'utf8mb4_site_local', 'charset_name' => 'utf8mb4']);
        $connection->method('fetchOne')->willThrowException(new RuntimeException('SELECT command denied'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not compare two values in "utf8mb4_site_local"');

        (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);
    }

    public function testAProbeWithNoAnswerRaisesInsteadOfReadingItAsNoFolding(): void
    {
        // fetchOne() returns false when the statement produced no row. A gate that reads that as "0", and "0" as
        // "this collation does not fold", exits 0 over a table it never managed to ask about.
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], ['api_token' => 'utf8mb4_site_local'], [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')], 'x', []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned no usable answer when asked whether "utf8mb4_site_local" folds case');

        (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);
    }

    public function testACollationNameTheCatalogueReportsOddlyIsNotWrittenIntoTheProbe(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('getParams')->willReturn([]);
        $connection->method('fetchFirstColumn')->willReturn(['utf8mb4_0900_bin']);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            fn (string $sql): array => str_contains($sql, 'information_schema.TABLES')
                ? $this->tableRows(['api_token' => 'utf8mb4_site local'], 'x')
                : [$this->column('api_token', 'token_hash', 'utf8mb4_0900_bin')],
        );
        $connection->method('fetchAssociative')->willReturn(['collation_name' => 'utf8mb4_site local', 'charset_name' => 'utf8mb4']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('utf8mb4_site local');

        (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($connection);
    }

    public function testATableWithOnlyIgnoredOrInactiveColumnsHasNoTableFinding(): void
    {
        $connection = $this->connection(
            new MySQLPlatform(),
            ['utf8mb4_0900_bin'],
            ['moved' => 'utf8mb4_0900_ai_ci', 'off' => 'utf8mb4_0900_ai_ci'],
            [$this->column('moved', 'subject_id', 'utf8mb4_general_ci')],
        );
        $report = (new ExactKeyChecker(
            [new ExactKeyColumns(
                new ExactKeyColumn('moved', 'subject_id', 'auditkit'),
                new ExactKeyColumn('off', 'subject_id', 'auditkit', '', 'the mode that creates it is off', false),
            )],
            ['moved.subject_id' => 'the host keeps this one in its own schema'],
        ))->check($connection);

        self::assertSame([], $report->tableFindings, 'The gate has no claim on a table this declaration no longer describes.');
        self::assertTrue($report->isOk());
    }

    public function testATableThatIsNotInTheSchemaIsReportedOnceAsAMissingColumn(): void
    {
        $connection = $this->connection(new MySQLPlatform(), ['utf8mb4_0900_bin'], [], []);
        $report     = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('never_migrated', 'token_hash', 'api-token'))]))->check($connection);

        self::assertCount(1, $report->failures());
        self::assertSame(ExactKeyStatus::MISSING, $report->failures()[0]->status);
        self::assertSame([], $report->tableFindings);
    }

    public function testSqliteHasNoTableLevelCollationAndIsLeftAlone(): void
    {
        $report = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($this->connection($this->sqlitePlatform()));

        self::assertFalse($report->supported);
        self::assertTrue($report->isOk());
        self::assertSame([], $report->tableFindings);
    }

    public function testPostgreSqlHasNoTableLevelCollationAndIsLeftAlone(): void
    {
        $report = (new ExactKeyChecker([new ExactKeyColumns(new ExactKeyColumn('api_token', 'token_hash', 'api-token'))]))->check($this->connection(new PostgreSQLPlatform()));

        self::assertFalse($report->supported);
        self::assertSame([], $report->tableFindings);
    }
}
