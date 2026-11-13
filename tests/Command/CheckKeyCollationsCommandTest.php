<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Persistence\ConnectionRegistry;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\Command\CheckKeyCollationsCommand;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumns;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

use function str_contains;

/**
 * The fleet's REQ-SEC-006 gate. These tests drive the command through the real {@see ExactKeyChecker} against a fake
 * information_schema, because what has to be pinned is not the exit codes but *what the gate looks at*: the positive
 * list of columns the installed bundles declare. A green run must mean "every declared column was found and is binary
 * NO PAD", never "nothing looked wrong".
 */
#[CoversClass(CheckKeyCollationsCommand::class)]
final class CheckKeyCollationsCommandTest extends TestCase
{
    /**
     * @param list<string> $collations binary NO PAD collations the fake server offers ([] = MySQL 5.7 / 8.0.0-8.0.16)
     * @param list<string> $tables base tables that exist
     * @param list<array<string, mixed>> $columns information_schema.COLUMNS rows
     */
    private function connection(array $collations = ['utf8mb4_0900_bin'], array $tables = [], array $columns = [], ?AbstractPlatform $platform = null): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform ?? new MySQLPlatform());
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static fn (string $sql): array => str_contains($sql, 'COLLATIONS') ? $collations : $tables,
        );
        $connection->method('fetchAllAssociative')->willReturn($columns);

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $table, string $column, ?string $collation, string $type = 'varchar(64)', string $nullable = 'NO'): array
    {
        return ['table_name' => $table, 'column_name' => $column, 'collation_name' => $collation, 'column_type' => $type, 'is_nullable' => $nullable];
    }

    /**
     * @param list<ExactKeyColumn> $declared
     * @param array<string, string> $ignoreColumns
     */
    private function tester(object $connection, array $declared = [], array $ignoreColumns = [], ?string &$requested = null): CommandTester
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturnCallback(static function (?string $name) use ($connection, &$requested): object {
            $requested = $name;

            return $connection;
        });

        $providers = $declared === [] ? [] : [new ExactKeyColumns(...$declared)];

        return new CommandTester(new CheckKeyCollationsCommand($registry, new ExactKeyChecker($providers, $ignoreColumns), 'default'));
    }

    public function testACleanSchemaPassesAndTheSummaryCountsEveryDeclaredColumn(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['audit_entry', 'user_session'], [
                $this->row('audit_entry', 'actor_id', 'utf8mb4_0900_bin'),
                $this->row('user_session', 'token', null, 'varbinary(64)'),
            ]),
            [
                new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle', 'who acted'),
                new ExactKeyColumn('user_session', 'token', 'nowo-tech/user-kit-bundle', 'session token'),
            ],
            [],
            $requested,
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('default', $requested);
        self::assertStringContainsString(
            'OK: 2 declared identity / access-key column(s) from 1 bundle(s) on mysql (2 ok, 0 fallback, 0 ignored by host, 0 skipped: mode off).',
            $tester->getDisplay(),
        );
    }

    public function testVerboseListsEveryPassingColumnWithTheBundleThatDeclaredIt(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['audit_entry'], [$this->row('audit_entry', 'actor_id', 'utf8mb4_0900_bin')]),
            [new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle', 'who acted')],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringNotContainsString('audit_entry.actor_id', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        // "%-8s " padding, the collation found, the column type, and the owning package in brackets.
        self::assertStringContainsString('ok       audit_entry.actor_id: utf8mb4_0900_bin (varchar(64)) [nowo-tech/audit-kit-bundle]', $tester->getDisplay());
    }

    public function testUtf8mb4BinFailsOnAServerThatOffersNoPadAndPrintsTheAlterStatement(): void
    {
        // The trap: utf8mb4_bin is binary, so it looks right, but it is PAD SPACE - "abc" and "abc   " are one key.
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['api_token'], [$this->row('api_token', 'token_hash', 'utf8mb4_bin')]),
            [new ExactKeyColumn('api_token', 'token_hash', 'nowo-tech/api-token-bundle', 'bearer token hash')],
        );

        self::assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('fail     api_token.token_hash: utf8mb4_bin (bearer token hash) [nowo-tech/api-token-bundle]', $display);
        self::assertStringContainsString('FAIL: 1 declared identity / access-key column(s) are not binary NO PAD on mysql (expected utf8mb4_0900_bin).', $display);
        self::assertStringContainsString('ALTER TABLE `api_token` MODIFY `token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL;', $display);
    }

    public function testTheServerDefaultCollationFails(): void
    {
        // Nobody ever wrote a COLLATE, so the column inherited utf8mb4_0900_ai_ci: case- and accent-insensitive AND
        // PAD SPACE. No scan for "bad" collations would have been asked about this column; the declaration is.
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['vault_grant'], [$this->row('vault_grant', 'grantee_id', 'utf8mb4_0900_ai_ci', 'varchar(191)', 'YES')]),
            [new ExactKeyColumn('vault_grant', 'grantee_id', 'nowo-tech/vault-bundle')],
        );

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('COLLATE utf8mb4_0900_bin NULL;', $tester->getDisplay());
    }

    public function testAColumnWithNoCollationAtAllPasses(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['user_session'], [$this->row('user_session', 'id', null, 'binary(16)')]),
            [new ExactKeyColumn('user_session', 'id', 'nowo-tech/user-kit-bundle')],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertStringContainsString('ok       user_session.id: (none) (binary(16): no collation, compared byte for byte)', $tester->getDisplay());
    }

    public function testADeclaredColumnThatDoesNotExistFailsEvenThoughItsTableDoes(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['chat_conversation'], [$this->row('chat_conversation', 'id', null, 'int')]),
            [new ExactKeyColumn('chat_conversation', 'external_id', 'nowo-tech/chat-kit-bundle')],
        );

        self::assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('missing  chat_conversation.external_id (table exists, column does not: renamed or dropped?) [nowo-tech/chat-kit-bundle]', $display);
        // A missing column has nothing to ALTER, so the failure list must not print an empty indented line for it.
        self::assertStringNotContainsString('ALTER TABLE', $display);
    }

    public function testADeclaredColumnWhoseTableIsAbsentFails(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], [], []),
            [new ExactKeyColumn('wf_instance', 'owner_id', 'nowo-tech/workflow-bundle')],
        );

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('missing  wf_instance.owner_id (table not in the schema: renamed, prefixed differently, or the migration has not run)', $tester->getDisplay());
    }

    public function testOnlyDeclaredColumnsAreChecked(): void
    {
        // The design principle: a positive list, not a scan. `note` is in the worst possible collation and sits in a
        // table the gate reads - and it is not an identity key, so it is not declared and not checked. If this test
        // ever starts failing, the command has become a scanner, and a scanner can only find what someone listed as
        // bad; it can never find the identity column nobody thought about.
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], ['audit_entry'], [
                $this->row('audit_entry', 'actor_id', 'utf8mb4_0900_bin'),
                $this->row('audit_entry', 'note', 'utf8mb4_0900_ai_ci', 'text'),
            ]),
            [new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle')],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('(1 ok, 0 fallback, 0 ignored by host, 0 skipped: mode off).', $tester->getDisplay());
        self::assertStringNotContainsString('note', $tester->getDisplay());
    }

    public function testADegradedServerAcceptsUtf8mb4BinAsAFallbackAndSaysWhy(): void
    {
        $tester = $this->tester(
            $this->connection([], ['api_token'], [$this->row('api_token', 'token_hash', 'utf8mb4_bin')]),
            [new ExactKeyColumn('api_token', 'token_hash', 'nowo-tech/api-token-bundle')],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('fallback api_token.token_hash: utf8mb4_bin (binary but PAD SPACE; this server offers no NO PAD collation, so this is the best available) [nowo-tech/api-token-bundle]', $display);
        self::assertStringContainsString('This server offers no NO PAD collation (MySQL 5.7 or 8.0.0-8.0.16): utf8mb4_bin is accepted as the documented fallback.', $display);
        self::assertStringContainsString('(0 ok, 1 fallback, 0 ignored by host, 0 skipped: mode off).', $display);
    }

    public function testAHostIgnoredColumnStaysInTheOutputWithItsReason(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_nopad_bin'], ['legal_document'], [$this->row('legal_document', 'slug', 'utf8mb4_unicode_ci')], new MariaDBPlatform()),
            [new ExactKeyColumn('legal_document', 'slug', 'nowo-tech/legal-kit-bundle')],
            ['legal_document.slug' => 'legacy slugs are compared case-insensitively on purpose'],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('ignored  legal_document.slug: utf8mb4_unicode_ci (ignored by host: legacy slugs are compared case-insensitively on purpose) [nowo-tech/legal-kit-bundle]', $display);
        self::assertStringContainsString('on mariadb (0 ok, 0 fallback, 1 ignored by host, 0 skipped: mode off).', $display);
    }

    public function testAnIgnoredColumnThatIsAlsoAbsentIsReportedAsBothAndDoesNotFail(): void
    {
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], [], []),
            [new ExactKeyColumn('legal_document', 'slug', 'nowo-tech/legal-kit-bundle')],
            ['legal_*.slug' => 'moved to the host table'],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('ignored  legal_document.slug (ignored by host: moved to the host table; also table not in the schema: renamed, prefixed differently, or the migration has not run)', $tester->getDisplay());
    }

    public function testAnInactiveColumnIsSkippedAndOnlyShownWhenVerbose(): void
    {
        $declared = [
            new ExactKeyColumn('chat_user_reference', 'user_key', 'nowo-tech/chat-kit-bundle', 'user key', 'nowo_chat_kit.user_reference: string', false),
            new ExactKeyColumn('chat_conversation', 'external_id', 'nowo-tech/chat-kit-bundle'),
        ];
        $connection = $this->connection(['utf8mb4_0900_bin'], ['chat_conversation'], [$this->row('chat_conversation', 'external_id', 'utf8mb4_0900_bin')]);

        $tester = $this->tester($connection, $declared);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringNotContainsString('chat_user_reference', $tester->getDisplay());
        self::assertStringContainsString('(1 ok, 0 fallback, 0 ignored by host, 1 skipped: mode off).', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertStringContainsString('skipped  chat_user_reference.user_key (nowo_chat_kit.user_reference: string) [nowo-tech/chat-kit-bundle]', $tester->getDisplay());
    }

    public function testNothingDeclaredIsItsOwnMessageAndNotAnAllClear(): void
    {
        // "Nothing to check" and "everything checked and fine" are both exit 0, so the only thing telling a host
        // which of the two it got is this message. It must not read like a pass, and it must not be the OK summary.
        $tester = $this->tester($this->connection());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('No identity / access-key column declared by any installed bundle.', $display);
        self::assertStringNotContainsString('OK:', $display);
        self::assertStringNotContainsString('FAIL:', $display);
    }

    public function testARunInWhichEveryColumnWasSkippedStillExitsZeroButSaysSoInTheCounts(): void
    {
        // A host with every declaring bundle's mode off checked nothing, and the command exits 0. The counts are the
        // only signal, so they have to carry it: 0 ok and 0 fallback with a non-zero skipped count.
        $tester = $this->tester(
            $this->connection(['utf8mb4_0900_bin'], [], []),
            [new ExactKeyColumn('chat_user_reference', 'user_key', 'nowo-tech/chat-kit-bundle', 'user key', 'nowo_chat_kit.user_reference: string', false)],
        );

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('OK: 1 declared identity / access-key column(s) from 1 bundle(s) on mysql (0 ok, 0 fallback, 0 ignored by host, 1 skipped: mode off).', $tester->getDisplay());
    }

    public function testAPlatformWithoutNoPadCollationsIsSkipped(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createMock(AbstractPlatform::class));

        $tester = $this->tester($connection, [new ExactKeyColumn('t', 'c', 'o')]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('SKIP: no NO PAD collation question on ', $tester->getDisplay());
        self::assertStringContainsString('(MySQL / MariaDB only).', $tester->getDisplay());
    }

    public function testTheConnectionOptionOverridesTheDefault(): void
    {
        $tester = $this->tester($this->connection(), [], [], $named);

        self::assertSame(Command::SUCCESS, $tester->execute(['--connection' => 'legacy']));
        self::assertSame('legacy', $named);
    }

    public function testAnEmptyConnectionOptionFallsBackToTheConfiguredOne(): void
    {
        $tester = $this->tester($this->connection(), [], [], $fallback);

        self::assertSame(Command::SUCCESS, $tester->execute(['--connection' => '']));
        self::assertSame('default', $fallback);
    }

    public function testANonDbalConnectionExitsMisconfigured(): void
    {
        $tester = $this->tester(new stdClass());

        self::assertSame(CheckKeyCollationsCommand::EXIT_MISCONFIGURED, $tester->execute(['--connection' => 'odd']));
        self::assertStringContainsString('Connection "odd" is not a DBAL connection.', $tester->getDisplay());
    }

    public function testAnUnreadableCollationCatalogueExitsMisconfiguredInsteadOfPassing(): void
    {
        // The gate's worst outcome is a false all clear. A connection that cannot read information_schema.COLLATIONS
        // knows nothing about the NO PAD collations the server offers, so it must not fall back to accepting
        // utf8mb4_bin (binary but PAD SPACE) and report OK.
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('fetchFirstColumn')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, 'COLLATIONS')) {
                throw new RuntimeException('SELECT command denied for table COLLATIONS');
            }

            return ['api_token'];
        });
        $connection->method('fetchAllAssociative')->willReturn([$this->row('api_token', 'token_hash', 'utf8mb4_bin')]);

        $tester = $this->tester($connection, [new ExactKeyColumn('api_token', 'token_hash', 'nowo-tech/api-token-bundle')]);

        self::assertSame(CheckKeyCollationsCommand::EXIT_MISCONFIGURED, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Cannot read information_schema.COLLATIONS', $display);
        self::assertStringNotContainsString('OK:', $display);
        self::assertStringNotContainsString('fallback', $display);
    }

    public function testADatabaseThatCannotBeReadExitsMisconfiguredAndNeverGreen(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willThrowException(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused <x>'));

        $tester = $this->tester($connection, [new ExactKeyColumn('api_token', 'token_hash', 'nowo-tech/api-token-bundle')]);

        self::assertSame(CheckKeyCollationsCommand::EXIT_MISCONFIGURED, $tester->execute([]));
        self::assertStringContainsString('SQLSTATE[HY000] [2002] Connection refused <x>', $tester->getDisplay());
    }
}
