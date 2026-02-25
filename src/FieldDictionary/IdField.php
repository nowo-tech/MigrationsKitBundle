<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\FieldDictionary;

/**
 * Field dictionary: reusable MDK definition for the standard primary key column "id".
 *
 * Use in migrations: include IdField::column() in MDK::COLUMNS and add primary key with IdField::primaryKey().
 */
final class IdField
{
    /**
     * Column: id (integer, autoincrement, notnull). Use as first column and primary key.
     *
     * @return array<string, mixed> MDK column definition
     */
    public static function column(): array
    {
        return [
            'name'          => 'id',
            'type'          => 'integer',
            'autoincrement' => true,
            'notnull'       => true,
        ];
    }

    /**
     * Primary key definition for the id column (MDK::PRIMARY_KEY).
     *
     * @return list<array{columns: list<string>}>
     */
    public static function primaryKey(): array
    {
        return [['columns' => ['id']]];
    }
}
