<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\Connection;
use Nowo\MigrationsKitBundle\Schema\DefaultTableOptions;

use function explode;
use function is_string;

/**
 * The one collation a host uses for ordinary text, and the character set that goes with it.
 *
 * Resolved exactly once, in one place, from the same chain {@see CollationChecker} has always used: an explicit
 * per-run override, then the bundle configuration (nowo_migrations_kit.collation_check.expected_collation), then
 * doctrine.dbal.default_table_options.collation (or its "collate" spelling).
 *
 * This is deliberately NOT the binary NO PAD collation of the declared identity / access-key columns. The uniform
 * collation is a normal one (utf8mb4_unicode_ci and friends); the key columns are binary NO PAD precisely because the
 * uniform one is not, and if everything were binary, declaring them would mean nothing. So a remedy for a table's
 * own default names the uniform collation, never the binary one: putting the binary collation on a table default
 * would fail the uniformity gate on every new table, make ordinary text comparisons case- and accent-sensitive (a
 * user-visible change), and leave the declared columns indistinguishable from the rest.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class UniformCollation
{
    /**
     * @param string|null $override an explicit value for this run (a --expected option)
     * @param string|null $configured the bundle configuration value
     *
     * @return string|null null when the host has declared no uniform collation anywhere
     */
    public static function collation(Connection $connection, ?string $override = null, ?string $configured = null): ?string
    {
        $options = DefaultTableOptions::forConnection($connection);

        return self::nonEmpty($override)
            ?? self::nonEmpty($configured)
            ?? self::nonEmpty($options['collation'] ?? null)
            ?? self::nonEmpty($options['collate'] ?? null);
    }

    /**
     * @param string|null $configured the configured character set, if any
     * @param string|null $collation when given, its prefix is the character set of last resort (MySQL: a collation
     *                               name starts with its character set)
     */
    public static function charset(Connection $connection, ?string $configured = null, ?string $collation = null): ?string
    {
        $options = DefaultTableOptions::forConnection($connection);
        $charset = self::nonEmpty($configured) ?? self::nonEmpty($options['charset'] ?? null);

        if ($charset !== null) {
            return $charset;
        }

        $collation = self::nonEmpty($collation);

        return $collation === null ? null : explode('_', $collation, 2)[0];
    }

    public static function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
