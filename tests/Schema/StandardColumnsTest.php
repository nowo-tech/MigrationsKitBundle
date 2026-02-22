<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema;

use Nowo\MigrationsKitBundle\Schema\StandardColumns;
use PHPUnit\Framework\TestCase;

class StandardColumnsTest extends TestCase
{
    public function testTimestampColumns(): void
    {
        $cols = StandardColumns::timestampColumns(true);
        self::assertArrayHasKey('created_at', $cols);
        self::assertArrayHasKey('updated_at', $cols);
        self::assertSame('datetime_immutable', $cols['created_at']['type']);
        self::assertFalse($cols['created_at']['notnull']);
    }

    public function testTimestampColumnsNotNull(): void
    {
        $cols = StandardColumns::timestampColumns(false);
        self::assertTrue($cols['created_at']['notnull']);
    }

    public function testUserRefColumns(): void
    {
        $cols = StandardColumns::userRefColumns(true);
        self::assertArrayHasKey('created_by', $cols);
        self::assertArrayHasKey('updated_by', $cols);
        self::assertSame('integer', $cols['created_by']['type']);
    }

    public function testAuditColumns(): void
    {
        $cols = StandardColumns::auditColumns(true);
        self::assertArrayHasKey('created_at', $cols);
        self::assertArrayHasKey('updated_by', $cols);
        self::assertCount(4, $cols);
    }

    public function testAuditIndexes(): void
    {
        $indexes = StandardColumns::auditIndexes();
        self::assertArrayHasKey('idx_created_by', $indexes);
        self::assertArrayHasKey('idx_updated_by', $indexes);
        self::assertSame(['created_by'], $indexes['idx_created_by']['columns']);
    }

    public function testTimestampColumnStepsSqlite(): void
    {
        $steps = StandardColumns::timestampColumnSteps('users', true);
        self::assertCount(2, $steps);
        self::assertSame('created_at', $steps[0]['column']);
        self::assertStringContainsString('ADD COLUMN', $steps[0]['add_sql']);
        self::assertStringContainsString('"users"', $steps[0]['add_sql']);
    }

    public function testTimestampColumnStepsMysql(): void
    {
        $steps = StandardColumns::timestampColumnSteps('users', false);
        self::assertCount(2, $steps);
        self::assertStringContainsString('ADD created_at', $steps[0]['add_sql']);
        self::assertStringContainsString('`users`', $steps[0]['add_sql']);
    }

    public function testUserRefColumnStepsSqlite(): void
    {
        $steps = StandardColumns::userRefColumnSteps('users', true);
        self::assertCount(2, $steps);
        self::assertSame('created_by', $steps[0]['column']);
        self::assertStringContainsString('INTEGER', $steps[0]['add_sql']);
    }

    public function testUserRefColumnStepsMysql(): void
    {
        $steps = StandardColumns::userRefColumnSteps('users', false);
        self::assertStringContainsString('INT', $steps[0]['add_sql']);
    }

    public function testAuditColumnSteps(): void
    {
        $steps = StandardColumns::auditColumnSteps('users', true);
        self::assertCount(4, $steps);
        self::assertSame('created_at', $steps[0]['column']);
        self::assertSame('updated_by', $steps[3]['column']);
    }

    public function testAuditIndexSteps(): void
    {
        $steps = StandardColumns::auditIndexSteps('users', false);
        self::assertCount(2, $steps);
        self::assertSame('idx_created_by', $steps[0]['index']);
        self::assertStringContainsString('CREATE INDEX', $steps[0]['add_sql']);
    }

    public function testAuditIndexStepsSqliteQuotes(): void
    {
        $steps = StandardColumns::auditIndexSteps('my_table', true);
        self::assertStringContainsString('"my_table"', $steps[0]['add_sql']);
    }
}
