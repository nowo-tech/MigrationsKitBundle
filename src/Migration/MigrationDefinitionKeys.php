<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

/**
 * Standard keys for migration definitions and declarative schema.
 *
 * Use these constants instead of string literals to standardize and lock the vocabulary
 * (similar to Doctrine DBAL Types). Required when building arrays for MigrationDefinitionRunner::run(),
 * MigrationDefinition::fromArray(), or SchemaSync / SchemaDefinitionParser.
 */
final class MigrationDefinitionKeys
{
    /** Top-level: table definitions (create_sql per table). */
    public const TABLES = 'tables';

    /** Top-level: column add steps (table, column, add_sql). */
    public const COLUMNS = 'columns';

    /** Top-level: index add steps (table, index_name, add_sql). */
    public const INDEXES = 'indexes';

    /** Top-level: column rename steps (table, old_name, new_name, rename_sql). */
    public const RENAME_COLUMNS = 'rename_columns';

    /** Top-level: column modify steps (table, column, modify_sql). */
    public const MODIFY_COLUMNS = 'modify_columns';

    /** Top-level: index drop steps (table, index_name, drop_sql). */
    public const DROP_INDEXES = 'drop_indexes';

    /** Top-level: column drop steps (table, column, drop_sql). */
    public const DROP_COLUMNS = 'drop_columns';

    /** Top-level: data steps (insert / update). */
    public const DATA = 'data';

    /** Data step type: insert (table, row, only_if_not_exists). */
    public const INSERT = 'insert';

    /** Data step type: update (table, set, where, only_if_exists). */
    public const UPDATE = 'update';

    /** Table definition (declarative): primary key column names. */
    public const PRIMARY_KEY = 'primary_key';

    /**
     * All top-level definition keys recognized by MigrationDefinitionRunner::run().
     *
     * @return list<string>
     */
    public static function allTopLevel(): array
    {
        return [
            self::TABLES,
            self::COLUMNS,
            self::INDEXES,
            self::RENAME_COLUMNS,
            self::MODIFY_COLUMNS,
            self::DROP_INDEXES,
            self::DROP_COLUMNS,
            self::DATA,
        ];
    }
}
