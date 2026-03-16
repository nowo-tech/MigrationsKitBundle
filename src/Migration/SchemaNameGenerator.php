<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

/**
 * Generates deterministic names for primary keys, indexes and foreign keys.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SchemaNameGenerator
{
    private const PK_PREFIX   = 'PK_';
    private const IDX_PREFIX  = 'IDX_';
    private const FK_PREFIX   = 'FK_';
    private const HASH_LENGTH = 16;

    /**
     * @param list<string> $columns
     */
    public static function generatePKName(string $tableName, array $columns): string
    {
        return self::PK_PREFIX . self::hashSuffix($tableName, $columns);
    }

    /**
     * @param list<string> $columns
     */
    public static function generateIndexName(string $tableName, array $columns): string
    {
        return self::IDX_PREFIX . self::hashSuffix($tableName, $columns);
    }

    /**
     * @param list<string> $columns
     */
    public static function generateForeignKeyName(string $tableName, array $columns): string
    {
        return self::FK_PREFIX . self::hashSuffix($tableName, $columns);
    }

    /**
     * @param list<string> $columns
     */
    private static function hashSuffix(string $tableName, array $columns): string
    {
        $key = $tableName . "\0" . implode("\0", $columns);

        return substr(md5($key), 0, self::HASH_LENGTH);
    }
}
