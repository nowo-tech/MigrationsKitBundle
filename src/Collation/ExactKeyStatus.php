<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

/**
 * Outcome of checking one declared identity / access-key column.
 *
 * Three outcomes, not two: a declared column may legitimately not exist because the mode that creates it is off
 * (SKIPPED). That is counted separately and never fails, or every host gets a wall of false failures for modes it
 * does not use. It must not become a hiding place either, which is why absence without a declared condition is
 * MISSING and does fail: a renamed or dropped table is a fault, a disabled feature is not.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
enum ExactKeyStatus: string
{
    /** Column exists and is binary NO PAD. */
    case OK = 'ok';

    /** Column exists, is binary, but the server offers no NO PAD collation (MySQL 5.7, 8.0.0-8.0.16). Passes, warns. */
    case FALLBACK = 'fallback';

    /** Column exists and is not binary NO PAD. Fails. */
    case FAIL = 'fail';

    /** Declared, active, and the table or column is not in the schema: renamed, dropped, or never migrated. Fails. */
    case MISSING = 'missing';

    /** Declared inactive: the configuration that creates the column is off. Not a failure. */
    case SKIPPED = 'skipped';

    /** The host deliberately put the column on another collation. Not a failure, but always shown with its reason. */
    case IGNORED = 'ignored';

    public function isFailure(): bool
    {
        return $this === self::FAIL || $this === self::MISSING;
    }
}
