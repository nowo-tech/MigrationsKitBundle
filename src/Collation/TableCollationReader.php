<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

use function sprintf;

/**
 * Reads information_schema.TABLES.TABLE_COLLATION: the default collation of a table, on MySQL / MariaDB.
 *
 * A table's own default is not the collation of its columns. Both gates of this bundle need it, so both read it from
 * here: {@see CollationChecker} over every base table, {@see ExactKeyChecker} over the tables that hold a declared
 * identity / access-key column. One query shape, one spelling of the remedy.
 *
 * PostgreSQL and SQLite have no table-level collation, which is why there is no query for them here; the callers ask
 * the platform before they ask this class.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class TableCollationReader
{
    private const SQL = "SELECT TABLE_NAME AS table_name, TABLE_COLLATION AS collation_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'";

    /**
     * @param list<string>|null $lowerNames lower-cased table names to restrict the read to; null = every base table
     *
     * @return list<array{table_name: string, collation_name: string}> collation_name is "" when the catalogue has no
     *                                                                 value for the table: never silently a pass
     */
    public static function mysql(Connection $connection, ?array $lowerNames = null): array
    {
        if ($lowerNames === []) {
            return [];
        }

        $rows = $lowerNames === null
            ? $connection->fetchAllAssociative(self::SQL . ' ORDER BY TABLE_NAME')
            : $connection->fetchAllAssociative(self::SQL . ' AND LOWER(TABLE_NAME) IN (?) ORDER BY TABLE_NAME', [$lowerNames], [ArrayParameterType::STRING]);

        $tables = [];
        foreach ($rows as $row) {
            $tables[] = [
                'table_name'     => (string) ($row['table_name'] ?? ''),
                'collation_name' => (string) ($row['collation_name'] ?? ''),
            ];
        }

        return $tables;
    }

    /**
     * The statement that changes a table's own default, and nothing else.
     *
     * DEFAULT CHARACTER SET … COLLATE … changes what future columns inherit. It is deliberately not CONVERT TO
     * CHARACTER SET …, which rewrites every existing text column of the table into the new collation - including the
     * declared identity / access-key columns, which would lose their binary collation and start folding the
     * look-alike keys REQ-SEC-006 exists to keep distinct.
     */
    public static function alter(string $table, string $charset, string $collation): string
    {
        return sprintf('ALTER TABLE `%s` DEFAULT CHARACTER SET %s COLLATE %s;', $table, $charset, $collation);
    }
}
