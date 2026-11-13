<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

/**
 * A bundle declares the identity and access-key columns of its own tables (REQ-SEC-006).
 *
 * Implement it on a small service of the bundle that owns the tables, built from the bundle's own configuration so
 * that table names are resolved at runtime (prefixes, host-chosen names) and columns of a mode that is off are
 * declared inactive rather than omitted.
 *
 * Services implementing it are tagged nowo_migrations_kit.exact_key_columns automatically (autoconfiguration), so an
 * installed bundle is discovered by the host with no registration: nowo:migrations:check-key-collations then asserts
 * every installed bundle's declaration against the host's live schema in one pass.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface ExactKeyColumnsInterface
{
    /**
     * Must not query the database, and must be safe to call more than once per process (FrankenPHP worker mode).
     *
     * @return list<ExactKeyColumn>
     */
    public function exactKeyColumns(): array;
}
