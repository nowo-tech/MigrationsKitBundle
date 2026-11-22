<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;

/**
 * The same folding table default, on a bundle that did NOT opt in: the gate passes.
 *
 * The damage this gate chases lives in a host's database, not in a bundle's test DSN — a test schema holds no real
 * identities. Failing ~40 bundles on a MigrationsKit bump they took for unrelated reasons would produce failures
 * about test infrastructure rather than about the bundle, and failures like that get silenced instead of fixed,
 * which would cost the gate and the trust in it. The host-side command is where the check is mandatory.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class OptedOutTableDefaultGate extends FakeMySqlExactKeyColumnsTestCase
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
