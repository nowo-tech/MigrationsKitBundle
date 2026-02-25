<?php

declare(strict_types=1);

namespace DoctrineMigrations\FieldDictionary;

/**
 * Field dictionary: reusable MDK definitions for common audit columns.
 *
 * Use in migrations in two phases (recommended):
 *   Phase 1 – add column only: include AuditFields::createdBy() in MDK::COLUMNS.
 *   Phase 2 – add foreign key: include AuditFields::createdByForeignKey($userTable) in MDK::FOREIGN_KEYS.
 *
 * Timestamps (no FK): use createdAt(), updatedAt() in MDK::COLUMNS.
 * User references: use createdBy()/updatedBy() for columns, then createdByForeignKey()/updatedByForeignKey() for FKs.
 */
final class AuditFields
{
    // --- Timestamp columns (no foreign key) ---

    /**
     * Column: created_at (datetime_immutable, nullable).
     *
     * @return array<string, mixed> MDK column definition
     */
    public static function createdAt(): array
    {
        return [
            'name' => 'created_at',
            'type' => 'datetime_immutable',
            'notnull' => false,
        ];
    }

    /**
     * Column: updated_at (datetime_immutable, nullable).
     *
     * @return array<string, mixed> MDK column definition
     */
    public static function updatedAt(): array
    {
        return [
            'name' => 'updated_at',
            'type' => 'datetime_immutable',
            'notnull' => false,
        ];
    }

    /**
     * Both timestamp columns for MDK::COLUMNS.
     *
     * @return list<array<string, mixed>>
     */
    public static function timestampColumns(): array
    {
        return [self::createdAt(), self::updatedAt()];
    }

    // --- User reference columns (phase 1: column only) ---

    /**
     * Column: created_by (integer, nullable). Add in phase 1; add FK in phase 2 with createdByForeignKey().
     *
     * @return array<string, mixed> MDK column definition
     */
    public static function createdBy(): array
    {
        return [
            'name' => 'created_by',
            'type' => 'integer',
            'notnull' => false,
        ];
    }

    /**
     * Column: updated_by (integer, nullable). Add in phase 1; add FK in phase 2 with updatedByForeignKey().
     *
     * @return array<string, mixed> MDK column definition
     */
    public static function updatedBy(): array
    {
        return [
            'name' => 'updated_by',
            'type' => 'integer',
            'notnull' => false,
        ];
    }

    /**
     * Both user reference columns for MDK::COLUMNS (phase 1).
     *
     * @return list<array<string, mixed>>
     */
    public static function userRefColumns(): array
    {
        return [self::createdBy(), self::updatedBy()];
    }

    // --- Foreign key definitions (phase 2: FK to user table) ---

    /**
     * Foreign key: created_by -> $userTableName(id). Use in phase 2 after column exists.
     *
     * @return array<string, mixed> MDK foreign_keys item
     */
    public static function createdByForeignKey(string $userTableName, string $localColumn = 'created_by'): array
    {
        return [
            'columns' => [$localColumn],
            'foreign_table' => $userTableName,
            'foreign_columns' => ['id'],
        ];
    }

    /**
     * Foreign key: updated_by -> $userTableName(id). Use in phase 2 after column exists.
     *
     * @return array<string, mixed> MDK foreign_keys item
     */
    public static function updatedByForeignKey(string $userTableName, string $localColumn = 'updated_by'): array
    {
        return [
            'columns' => [$localColumn],
            'foreign_table' => $userTableName,
            'foreign_columns' => ['id'],
        ];
    }

    /**
     * Both FKs to user table for MDK::FOREIGN_KEYS (phase 2).
     *
     * @return list<array<string, mixed>>
     */
    public static function userRefForeignKeys(string $userTableName): array
    {
        return [
            self::createdByForeignKey($userTableName),
            self::updatedByForeignKey($userTableName),
        ];
    }
}
