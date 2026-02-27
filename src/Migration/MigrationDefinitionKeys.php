<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

/**
 * Keys for the migration definition array.
 *
 * Apply execution order (see docs/DECLARATIVE_SCHEMA.md):
 *   1. Drop FKs referencing DROP_TABLES; drop FKs by name (DROP_FOREIGN_KEYS); drop indexes (DROP_INDEXES).
 *   2. Drop columns, drop tables.
 *   3. Create or edit columns and tables (TABLES, COLUMNS, PRIMARY_KEY).
 *   4. Create indexes, foreign keys, unique.
 *
 * Use in migrations with the CreateTablesService::apply() flow:
 *   $schema = $this->connection->createSchemaManager()->introspectSchema();
 *   foreach ($service->apply($schema, $definition) as $sql) { $this->addSql($sql); }
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MigrationDefinitionKeys
{
    /** Top-level: list of table names to drop. Before dropping, the bundle drops any FK that references these tables. */
    public const DROP_TABLES = 'drop_tables';

    /** Top-level: map of table name => table definition. */
    public const TABLES = 'tables';

    /** Table definition: array of column definitions (name, type, length, notnull, default, etc.). */
    public const COLUMNS = 'columns';

    /** Table definition: primary key column name(s). e.g. ['columns' => ['id']]. */
    public const PRIMARY_KEY = 'primary_key';

    /** Table definition: array of foreign key definitions (columns, foreign_table, foreign_columns, optional name, onUpdate, onDelete). */
    public const FOREIGN_KEYS = 'foreign_keys';

    /** Table definition: list of foreign key names to drop. Emits ALTER TABLE … DROP FOREIGN KEY. */
    public const DROP_FOREIGN_KEYS = 'drop_foreign_keys';

    /** Table definition: list of index names to drop. Emits DROP INDEX. */
    public const DROP_INDEXES = 'drop_indexes';

    /** Table definition: list of column names to drop. Emits ALTER TABLE … DROP COLUMN. */
    public const DROP_COLUMNS = 'drop_columns';

    /** Table definition: drop primary key (e.g. empty list or true). Emits ALTER TABLE … DROP PRIMARY KEY. */
    public const DROP_PRIMARY_KEYS = 'drop_primary_keys';

    /** Column/table definition: mark for removal (e.g. drop column). */
    public const DROP = 'drop';

    /** Column definition: new name when renaming. e.g. ['name' => 'old_title', 'rename' => 'title']. */
    public const RENAME = 'rename';

    /** Table definition: array of index definitions. e.g. [['columns'=>['c1'], 'name'=>'idx_c1'], ['columns'=>['email'], 'unique'=>true]]. */
    public const INDEXES = 'indexes';

    // --- Foreign key referential actions (use as values for 'onDelete' / 'onUpdate' in FOREIGN_KEYS items) ---

    /** Value for onDelete/onUpdate: delete/update child rows when parent row is deleted/updated. */
    public const ON_DELETE_CASCADE = 'CASCADE';
    /** Value for onDelete: set referencing column to NULL when parent row is deleted (column must be nullable). */
    public const ON_DELETE_SET_NULL = 'SET NULL';
    /** Value for onDelete/onUpdate: prevent delete/update if rows reference the parent (default on many platforms). */
    public const ON_DELETE_RESTRICT = 'RESTRICT';
    /** Value for onDelete/onUpdate: no action (database-dependent; often same as RESTRICT). */
    public const ON_DELETE_NO_ACTION = 'NO ACTION';
    /** Value for onDelete/onUpdate: set referencing column to its default when parent is deleted/updated. */
    public const ON_DELETE_SET_DEFAULT = 'SET DEFAULT';

    /** Value for onUpdate: when parent key is updated, update the referencing column to match. */
    public const ON_UPDATE_CASCADE = 'CASCADE';
    /** Value for onUpdate: set referencing column to NULL when parent key is updated. */
    public const ON_UPDATE_SET_NULL = 'SET NULL';
    /** Value for onUpdate: prevent update of parent key if rows reference it. */
    public const ON_UPDATE_RESTRICT = 'RESTRICT';
    /** Value for onUpdate: no action. */
    public const ON_UPDATE_NO_ACTION = 'NO ACTION';
    /** Value for onUpdate: set referencing column to its default when parent key is updated. */
    public const ON_UPDATE_SET_DEFAULT = 'SET DEFAULT';
}
