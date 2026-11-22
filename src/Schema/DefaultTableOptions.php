<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema;

use Doctrine\DBAL\Connection;

/**
 * The table options Doctrine already computes for a connection (doctrine.dbal.default_table_options).
 *
 * One source, read the way DBAL itself reads it in AbstractSchemaManager::createSchemaConfig(): the connection
 * parameters, plus the connection charset as the charset of last resort. Taken from the parameters rather than from
 * createSchemaConfig() on purpose: that call also asks the server for the current schema name and the platform for the
 * identifier length, neither of which a definition parser needs, and both of which turn a pure array lookup into a
 * round trip.
 *
 * Why this exists at all: a Table built as `new Table($name)` carries no options, so a generated CREATE TABLE omits
 * the charset and collation a host configured, and the table lands on the server default instead - typically
 * utf8mb4_0900_ai_ci. The columns are unaffected (they are created with whatever the definition says), but the
 * table's own default is what the first text column anyone adds later without an explicit COLLATE inherits.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DefaultTableOptions
{
    /**
     * @return array<string, mixed> empty when the host configured nothing: the generated SQL is then byte-identical
     *                              to what it was before these options were passed through
     */
    public static function forConnection(Connection $connection): array
    {
        $params  = $connection->getParams();
        $options = $params['defaultTableOptions'] ?? [];

        $normalised = [];
        foreach ($options as $name => $value) {
            $normalised[(string) $name] = $value;
        }

        $charset = $params['charset'] ?? '';
        if (!isset($normalised['charset']) && $charset !== '') {
            $normalised['charset'] = $charset;
        }

        return $normalised;
    }
}
