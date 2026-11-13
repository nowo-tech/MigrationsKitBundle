<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyFinding;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumnsInterface;
use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_map;
use function class_exists;
use function fnmatch;
use function getenv;
use function implode;
use function is_string;
use function sprintf;
use function strtolower;

/**
 * Base test case giving a bundle its own REQ-SEC-006 gate, against the bundle's test database.
 *
 * This is the point of the declaration: a new identity column added to the bundle's schema and left on the host
 * default fails *this bundle's* CI, long before any host installs it. Extend it, return the bundle's own
 * {@see ExactKeyColumnsInterface} service(s), and create the schema in {@see createSchema()}.
 *
 * Skips cleanly when there is no database to talk to (no DATABASE_URL) or when the platform has no such notion
 * (PostgreSQL, SQLite), so a bundle whose unit suite runs without a database is not broken by extending it.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
abstract class ExactKeyColumnsTestCase extends TestCase
{
    /** DSN schemes the default connection understands; "pdo-mysql://", "mysql://", "mariadb://", … */
    private const DSN_SCHEMES = [
        'mysql'      => 'pdo_mysql',
        'mysql2'     => 'pdo_mysql',
        'mariadb'    => 'pdo_mysql',
        'pdo-mysql'  => 'pdo_mysql',
        'postgres'   => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'pgsql'      => 'pdo_pgsql',
        'pdo-pgsql'  => 'pdo_pgsql',
        'sqlite'     => 'pdo_sqlite',
        'pdo-sqlite' => 'pdo_sqlite',
    ];

    /**
     * The bundle's own declaration service(s), built exactly as the bundle builds them at runtime.
     *
     * @return list<ExactKeyColumnsInterface>
     */
    abstract protected function exactKeyColumnProviders(): array;

    /**
     * Create (or migrate) the declared tables on the test database. The default does nothing, for a suite whose
     * fixtures already ran.
     */
    protected function createSchema(Connection $connection): void
    {
    }

    /**
     * The `nowo_migrations_kit.collation_check.ignore_columns` keys (or fnmatch patterns) that this bundle, or the
     * host suite running it, keeps for the uniform collation gate. Default: none.
     *
     * Override it with the real list, and a declared exact-key column may never also appear in it. Two copies of the
     * same decision is how the two gates come to disagree: declaring a column already exempts it from the uniform
     * gate (since 2.5.0), so the ignore entry is redundant on the day it is written - and it outlives the
     * declaration. Drop the column from the declaration and the leftover entry goes on silently excusing a column
     * that nothing checks any more.
     *
     * @return list<string>
     */
    protected function uniformCollationIgnoreColumns(): array
    {
        return [];
    }

    /**
     * Environment variable holding the test database DSN. Override to use another name.
     */
    protected function databaseUrlVariable(): string
    {
        return 'DATABASE_URL';
    }

    final public function testDeclaredIdentityColumnsAreBinaryNoPad(): void
    {
        $connection = $this->createTestConnection();
        if (!$connection instanceof Connection) {
            self::markTestSkipped(sprintf('%s is not set: no database to check identity column collations against.', $this->databaseUrlVariable()));
        }
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Binary NO PAD collations are a MySQL / MariaDB question only.');
        }

        $this->createSchema($connection);

        $report = (new ExactKeyChecker($this->exactKeyColumnProviders()))->check($connection);

        self::assertNotSame([], $report->findings, 'This bundle declares no identity / access-key column. If it really owns none, do not extend ExactKeyColumnsTestCase; otherwise declare them, so a new one cannot land on the host default collation unnoticed.');

        self::assertSame(
            [],
            array_map(static fn (ExactKeyFinding $f): string => $f->describe(), $report->failures()),
            sprintf(
                "Identity / access-key columns must be binary NO PAD (REQ-SEC-006); expected %s on this server.\n%s",
                $report->expectedCollation,
                implode("\n", array_map(static fn (ExactKeyFinding $f): string => (string) $f->alter, $report->failures())),
            ),
        );

        // A column that only exists in an opt-in mode is skipped, not failed - but a suite where every declared
        // column is skipped proves nothing, so say so instead of reporting a green gate.
        self::assertNotSame(
            [],
            $report->withStatus(ExactKeyStatus::OK, ExactKeyStatus::FALLBACK),
            'Every declared column was skipped or ignored: this run checked nothing. Create the schema for at least one declared mode in createSchema().',
        );

        $restated = [];
        foreach ($report->findings as $finding) {
            foreach ($this->uniformCollationIgnoreColumns() as $pattern) {
                $pattern = strtolower($pattern);
                if ($pattern === $finding->column->key() || fnmatch($pattern, $finding->column->key())) {
                    $restated[] = sprintf('%s (ignore entry "%s")', $finding->column->describe(), $pattern);
                }
            }
        }

        self::assertSame(
            [],
            $restated,
            'These columns are declared exact keys and are also restated in collation_check.ignore_columns. The declaration already exempts them from the uniform collation gate, so the ignore entries add nothing today and outlive the declaration tomorrow: delete them.',
        );
    }

    protected function createTestConnection(): ?Connection
    {
        $url = getenv($this->databaseUrlVariable());
        if (!is_string($url) || $url === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $params */
            $params     = class_exists(DsnParser::class) ? (new DsnParser(self::DSN_SCHEMES))->parse($url) : ['url' => $url];
            $connection = DriverManager::getConnection($params + ['charset' => 'utf8mb4']);
            $connection->getDatabasePlatform();

            return $connection;
        } catch (Throwable) {
            return null;
        }
    }
}
