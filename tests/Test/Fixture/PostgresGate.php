<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;

/**
 * A bundle whose suite runs on PostgreSQL: binary NO PAD is a MySQL / MariaDB question, so the gate skips cleanly.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PostgresGate extends FakeMySqlExactKeyColumnsTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function declaredColumns(): array
    {
        return [new ExactKeyColumn('t', 'c', 'o')];
    }

    /**
     * {@inheritdoc}
     */
    protected function createPlatform(): AbstractPlatform
    {
        return new PostgreSQLPlatform();
    }
}
