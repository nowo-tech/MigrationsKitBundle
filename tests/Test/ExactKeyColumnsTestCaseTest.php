<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test;

use Doctrine\DBAL\Connection;
use Nowo\MigrationsKitBundle\Test\ExactKeyColumnsTestCase;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\AllSkippedGate;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\DoubleListedGate;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\FailingGate;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\NoDeclarationGate;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\PostgresGate;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\RealConnectionGate;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\SkippedWithMessageException;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

/**
 * ExactKeyColumnsTestCase is a test helper that ships in src/, so it is a host-facing contract: about forty bundles
 * are meant to extend it and get their own REQ-SEC-006 gate from it. These tests pin the guards that make that gate
 * worth having - a bundle that declares nothing, and a run in which every declared column was skipped, must both be
 * reported as "this checked nothing" rather than as a pass.
 */
#[CoversClass(ExactKeyColumnsTestCase::class)]
final class ExactKeyColumnsTestCaseTest extends TestCase
{
    public function testABundleThatDeclaresNothingIsToldNotToExtendTheGate(): void
    {
        $gate = new NoDeclarationGate('testDeclaredIdentityColumnsAreBinaryNoPad');

        try {
            $gate->testDeclaredIdentityColumnsAreBinaryNoPad();
            self::fail('A gate with no declaration must fail.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('This bundle declares no identity / access-key column.', $e->getMessage());
            self::assertStringContainsString('do not extend ExactKeyColumnsTestCase', $e->getMessage());
        }

        self::assertSame(1, $gate->createSchemaCalls, 'The bundle schema is created before the check, once.');
    }

    public function testADeclaredColumnOnTheServerDefaultFailsWithTheAlterThatFixesIt(): void
    {
        $gate = new FailingGate('testDeclaredIdentityColumnsAreBinaryNoPad');

        try {
            $gate->testDeclaredIdentityColumnsAreBinaryNoPad();
            self::fail('A column on utf8mb4_0900_ai_ci must fail the gate.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('Identity / access-key columns must be binary NO PAD (REQ-SEC-006); expected utf8mb4_0900_bin on this server.', $e->getMessage());
            self::assertStringContainsString('ALTER TABLE `vault_grant` MODIFY `grantee_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL;', $e->getMessage());
            self::assertStringContainsString('fail     vault_grant.grantee_id: utf8mb4_0900_ai_ci', (string) $e->getComparisonFailure()?->getDiff());
        }
    }

    public function testARunInWhichEveryColumnWasSkippedIsNotAPass(): void
    {
        $gate = new AllSkippedGate('testDeclaredIdentityColumnsAreBinaryNoPad');

        try {
            $gate->testDeclaredIdentityColumnsAreBinaryNoPad();
            self::fail('A run that checked nothing must not report a green gate.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('Every declared column was skipped or ignored: this run checked nothing.', $e->getMessage());
        }
    }

    public function testADeclaredColumnMayNotAlsoBeRestatedInTheHostIgnoreList(): void
    {
        // Two copies of the same decision is how the two gates come to disagree: declaring the column already exempts
        // it from the uniform collation gate, and the leftover ignore entry outlives the declaration.
        $gate = new DoubleListedGate('testDeclaredIdentityColumnsAreBinaryNoPad');

        try {
            $gate->testDeclaredIdentityColumnsAreBinaryNoPad();
            self::fail('A column that is both declared and ignored must be reported.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('The declaration already exempts them from the uniform collation gate', $e->getMessage());
            $diff = (string) $e->getComparisonFailure()?->getDiff();
            self::assertStringContainsString('audit_entry.actor_id (ignore entry "audit_entry.*")', $diff);
            self::assertStringNotContainsString('chat_', $diff, 'An entry that matches no declared column is none of the gate\'s business.');
        }
    }

    public function testAPlatformWithoutNoPadCollationsSkipsCleanly(): void
    {
        $this->expectException(SkippedWithMessageException::class);
        $this->expectExceptionMessage('Binary NO PAD collations are a MySQL / MariaDB question only.');

        (new PostgresGate('testDeclaredIdentityColumnsAreBinaryNoPad'))->testDeclaredIdentityColumnsAreBinaryNoPad();
    }

    public function testNoTestDatabaseSkipsAndNamesTheVariableItLookedFor(): void
    {
        $gate           = new RealConnectionGate('testDeclaredIdentityColumnsAreBinaryNoPad');
        $gate->variable = 'NOWO_MK_FIXTURE_DATABASE_URL';
        /* @phpstan-ignore-next-line frankenphp.classic.noPutenv (PHPUnit test: the DSN the shipped gate reads comes from the environment, so covering it means setting it; restored in finally) */
        putenv($gate->variable);

        $this->expectException(SkippedWithMessageException::class);
        $this->expectExceptionMessage('NOWO_MK_FIXTURE_DATABASE_URL is not set: no database to check identity column collations against.');

        $gate->testDeclaredIdentityColumnsAreBinaryNoPad();
    }

    public function testTheVariableDefaultsToDatabaseUrl(): void
    {
        self::assertSame('DATABASE_URL', (new RealConnectionGate('testDeclaredIdentityColumnsAreBinaryNoPad'))->variableName());
    }

    public function testAUsableDsnBecomesAConnectionAndAnUnusableOneBecomesNull(): void
    {
        $gate           = new RealConnectionGate('testDeclaredIdentityColumnsAreBinaryNoPad');
        $gate->variable = 'NOWO_MK_FIXTURE_DATABASE_URL';
        $previous       = getenv($gate->variable);

        try {
            /* @phpstan-ignore-next-line frankenphp.classic.noPutenv (PHPUnit test: the DSN the shipped gate reads comes from the environment, so covering it means setting it; restored in finally) */
            putenv($gate->variable . '=sqlite:///:memory:');
            self::assertInstanceOf(Connection::class, $gate->connect());

            /* @phpstan-ignore-next-line frankenphp.classic.noPutenv (PHPUnit test: the DSN the shipped gate reads comes from the environment, so covering it means setting it; restored in finally) */
            putenv($gate->variable . '=not a dsn at all');
            self::assertNull($gate->connect(), 'An unusable DSN must skip the gate, not break the suite.');

            /* @phpstan-ignore-next-line frankenphp.classic.noPutenv (PHPUnit test: the DSN the shipped gate reads comes from the environment, so covering it means setting it; restored in finally) */
            putenv($gate->variable . '=');
            self::assertNull($gate->connect());
        } finally {
            /* @phpstan-ignore-next-line frankenphp.classic.noPutenv (PHPUnit test: the DSN the shipped gate reads comes from the environment, so covering it means setting it; restored in finally) */
            $previous === false ? putenv($gate->variable) : putenv($gate->variable . '=' . $previous);
        }
    }
}
