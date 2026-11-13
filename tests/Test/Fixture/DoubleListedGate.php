<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;

/**
 * A host that declares its exact-key columns and also keeps the old collation_check.ignore_columns entries for them:
 * the gate must refuse the second copy of the list rather than let the two drift apart.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoubleListedGate extends FakeMySqlExactKeyColumnsTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function declaredColumns(): array
    {
        return [new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle', 'who acted')];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaTables(): array
    {
        return ['audit_entry'];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaColumns(): array
    {
        return [$this->row('audit_entry', 'actor_id', 'utf8mb4_0900_bin')];
    }

    /**
     * {@inheritdoc}
     */
    protected function uniformCollationIgnoreColumns(): array
    {
        return ['chat_*.user_key', 'Audit_Entry.*'];
    }
}
