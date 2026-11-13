<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Throwable;

use function implode;
use function in_array;
use function str_ends_with;
use function strtolower;

/**
 * Which binary NO PAD collations the connected server actually offers.
 *
 * Answered by asking the server (information_schema.COLLATIONS), never by parsing a version string. Every chooser in
 * the fleet does version_compare() on doctrine.dbal.server_version, which has to know that 8.0.17 added
 * utf8mb4_0900_bin, that 8.0.0-8.0.16 has none, that 5.7 has none, that MariaDB spells it utf8mb4_nopad_bin — and
 * which then gets MySQL 9.x, Percona or a fork wrong the moment the prefix stops being "8.". The server knows; ask it.
 *
 * Degraded servers (MySQL 5.7, MySQL 8.0.0-8.0.16) have no NO PAD collation at all. There utf8mb4_bin is the correct
 * answer and is accepted, with a warning: it is binary but PAD SPACE, so "abc" = "abc   " still holds.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactCollationSupport
{
    /** Preference order for the collation suggested in the ALTER statement. */
    private const PREFERRED = ['utf8mb4_0900_bin', 'utf8mb4_nopad_bin'];

    /** Looked up by name when the server has no PAD_ATTRIBUTE column (MariaDB, older forks). */
    private const KNOWN_NO_PAD = ['utf8mb4_0900_bin', 'utf8mb4_nopad_bin', 'utf8mb4_unicode_nopad_ci', 'utf8mb4_general_nopad_ci'];

    /**
     * @param bool $supported false on a platform where the question does not arise (PostgreSQL, SQLite, …)
     * @param list<string> $noPadBinary lower-cased binary NO PAD collations the server offers
     * @param string $expected collation suggested in the ALTER statement
     * @param bool $degraded true when the server has no NO PAD collation and utf8mb4_bin is the best available
     */
    private function __construct(
        public bool $supported,
        public array $noPadBinary,
        public string $expected,
        public bool $degraded,
    ) {
    }

    public static function unsupported(): self
    {
        return new self(false, [], '', false);
    }

    /**
     * @param list<string> $noPadBinary
     */
    public static function of(array $noPadBinary): self
    {
        foreach (self::PREFERRED as $preferred) {
            if (in_array($preferred, $noPadBinary, true)) {
                return new self(true, $noPadBinary, $preferred, false);
            }
        }

        return $noPadBinary === []
            ? new self(true, [], 'utf8mb4_bin', true)
            : new self(true, $noPadBinary, $noPadBinary[0], false);
    }

    public static function forConnection(Connection $connection): self
    {
        if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return self::unsupported();
        }

        // MySQL 8.0+ / 9.x: PAD_ATTRIBUTE says it outright. Binary collations end in _bin; the "binary" charset is
        // handled separately because it is not a utf8mb4 collation.
        $names = self::fetchNames($connection, "SELECT COLLATION_NAME FROM information_schema.COLLATIONS WHERE PAD_ATTRIBUTE = 'NO PAD' AND COLLATION_NAME LIKE '%\\_bin'");

        // MySQL 5.7 has no PAD_ATTRIBUTE column (the query errors); MariaDB may not expose it. Fall back to names.
        if ($names === []) {
            $names = self::fetchNames($connection, "SELECT COLLATION_NAME FROM information_schema.COLLATIONS WHERE COLLATION_NAME IN ('" . implode("','", self::KNOWN_NO_PAD) . "') AND COLLATION_NAME LIKE '%\\_bin'");
        }

        return self::of($names);
    }

    /**
     * A collation that compares identity keys byte for byte, with no padding.
     */
    public function accepts(string $collation): bool
    {
        $collation = strtolower($collation);

        return $collation === 'binary' || in_array($collation, $this->noPadBinary, true);
    }

    /**
     * Binary but PAD SPACE: correct only where the server offers nothing better (MySQL 5.7, 8.0.0-8.0.16).
     */
    public function isFallback(string $collation): bool
    {
        return $this->degraded && str_ends_with(strtolower($collation), '_bin');
    }

    /**
     * @return list<string>
     */
    private static function fetchNames(Connection $connection, string $sql): array
    {
        try {
            /** @var list<mixed> $rows */
            $rows = $connection->fetchFirstColumn($sql);
        } catch (Throwable) {
            return [];
        }

        $names = [];
        foreach ($rows as $value) {
            $names[] = strtolower((string) $value);
        }

        return $names;
    }
}
