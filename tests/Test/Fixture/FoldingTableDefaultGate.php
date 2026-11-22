<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;

/**
 * A bundle that opted in to the table-default assertion (uniformCollation()), with every declared column binary
 * NO PAD on a table whose own default is the MySQL 8 server default: the gate must fail. This is the state that
 * reads as a pass today and breaks the next time someone adds a text column.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class FoldingTableDefaultGate extends FakeMySqlExactKeyColumnsTestCase
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
    protected function uniformCollation(): string
    {
        return 'utf8mb4_unicode_ci';
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaTables(): array
    {
        return ['vault_grant' => 'utf8mb4_0900_ai_ci'];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaColumns(): array
    {
        return [$this->row('vault_grant', 'grantee_id', 'utf8mb4_0900_bin', 'varchar(191)')];
    }
}
