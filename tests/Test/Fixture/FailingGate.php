<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;

/**
 * A declared identity column left on the MySQL 8 server default (utf8mb4_0900_ai_ci): the gate must fail.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class FailingGate extends FakeMySqlExactKeyColumnsTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function declaredColumns(): array
    {
        return [new ExactKeyColumn('vault_grant', 'grantee_id', 'nowo-tech/vault-bundle')];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaTables(): array
    {
        return ['vault_grant'];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaColumns(): array
    {
        return [$this->row('vault_grant', 'grantee_id', 'utf8mb4_0900_ai_ci', 'varchar(191)')];
    }
}
