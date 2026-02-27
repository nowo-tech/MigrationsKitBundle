# Declarative schema — Migrations Kit Bundle

This document describes the **declarative schema definition** used with **CreateTablesService**. You describe the desired tables, columns, indexes and foreign keys in an array (keys from **MigrationDefinitionKeys**, alias **MDK**); the service uses the Doctrine Schema API and emits only the needed SQL.

**CreateTablesService:** Pass an **introspected** schema and a definition. The service checks table/column/index/FK existence and: creates tables that do not exist (with columns and primary key); **if the table does not exist and the definition only has column entries with RENAME** (no full column types), the service skips creation (nothing to create or rename). Otherwise: renames columns (Phase 3a, `RENAME`); modifies column type/options (Phase 3b); adds missing columns; creates indexes/unique (Phase 4a, `INDEXES`); creates foreign keys (Phase 4b). Drops: FKs that reference DROP_TABLES, FKs by name, indexes, columns, PK, tables (Phases 1–2). Column definitions with `MDK::DROP => true` are skipped when adding; use `DROP_COLUMNS` to drop columns.

**Requirements:** Doctrine DBAL 2.x, 3.x or 4.x (see [INSTALLATION.md](INSTALLATION.md#requirements)). Schema is passed from the migration's `up()`/`down()`; for accurate checks, use `$this->connection->createSchemaManager()->introspectSchema()`.

## Order of operations (apply execution order)

The bundle applies changes in this fixed order so that dependencies are respected (e.g. drop FKs before tables, create columns before indexes):

| Phase | Action | Status |
|-------|--------|--------|
| **1** | **Drop** FKs that reference tables in DROP_TABLES; drop FKs by name (DROP_FOREIGN_KEYS); drop indexes (DROP_INDEXES) | ✅ Implemented |
| **2** | **Drop** columns (DROP_COLUMNS), drop primary key (DROP_PRIMARY_KEYS), drop tables (DROP_TABLES) | ✅ Implemented |
| **3** | **Create or edit** tables (CREATE TABLE if missing); rename columns (RENAME); modify columns (type/options); add missing columns; add or change primary key on existing table | ✅ Implemented |
| **4** | **Create** indexes and unique (INDEXES); create foreign keys (FOREIGN_KEYS) | ✅ Implemented |

The sections below describe each key and operation in full. For a use-cases matrix and expected SQL per demo migration, see [DEMO_MIGRATIONS_REFERENCE.md](DEMO_MIGRATIONS_REFERENCE.md).

**Recommended:** Use **independent, well-ordered migrations**: when adding, first add the column, then (in a later migration) add the index, then add the foreign key; when dropping, first drop indexes/FKs, then drop columns. See [USAGE.md](USAGE.md#recommended-independent-well-ordered-migrations).

---

## MigrationDefinitionKeys (MDK)

Use the constants so keys are consistent and typo-free. The **MDK** alias is recommended:

```php
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

$definition = [
    MDK::TABLES => [
        'users' => [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => true],
            ],
            MDK::PRIMARY_KEY => [['columns' => ['id']]],
            MDK::INDEXES => [
                ['columns' => ['email'], 'unique' => true],
            ],
        ],
    ],
];
```

**Model: array of associative arrays.** For `columns`, `primary_key`, `indexes`, and `foreign_keys`, each value is an array of associative arrays. Each element describes one item (column, PK, index, FK). Use `drop: true` to remove it, or `rename: 'new_name'` where supported.

**Constants:** `TABLES`, `COLUMNS`, `PRIMARY_KEY`, `INDEXES`, `FOREIGN_KEYS`, `RENAME`, `DROP`, `DROP_TABLES`, `DROP_COLUMNS`, `DROP_INDEXES`, `DROP_FOREIGN_KEYS`, `DROP_PRIMARY_KEYS`. See the class docblock in `MigrationDefinitionKeys.php` for the full structure.

---

## Top-level structure

- **drop_tables** (optional): list of table names to drop. The bundle applies two phases: (1) drop any foreign key that **references** one of these tables (so the drop can succeed); (2) drop each table only if it exists. See demo migrations Version20250223100004 (simple drop) and Version20250223100006 (drop table that was referenced by another; migration 00005 drops the FK first).
- **tables** (required for create/edit): map of table name => table definition.

Each table definition can be:

- **Create or edit:** `columns`, `primary_key`, `indexes`, `foreign_keys` (arrays of associative arrays), and/or shortcuts `drop_columns`, `drop_indexes`, `drop_foreign_keys`, `drop_primary_keys`. To **drop** a table, list it in top-level **drop_tables** (the bundle does not support `drop => true` inside a table definition).

---

## Columns

Each column is an associative array. Identified by **name**. Other keys define the column or the operation.

| Key            | Description                    | Example                    |
|----------------|--------------------------------|----------------------------|
| `name`         | Column name (required)         | `'email'`                  |
| `type`         | DBAL type (required for add)  | `'string'`, `'integer'`   |
| `length`       | Length for string/decimal     | `180`                      |
| `precision`    | Precision for decimal          | `10`                       |
| `scale`        | Scale for decimal              | `2`                        |
| `notnull`      | Not null                       | `true`                     |
| `default`      | Default value                  | `null`, `0`                |
| `autoincrement`| Auto-increment                 | `true`                     |
| `comment`      | Column comment                 | `'User email'`             |
| **drop**       | Remove this column             | `true`                     |
| **rename**     | New name (rename column)       | `'new_name'`               |

**Note:** For a unique constraint on a column, use **INDEXES** with `unique => true` (e.g. `['columns' => ['email'], 'unique' => true]`). The column definition itself does not support a `unique` key.

**Example (add/update):**

```php
MDK::COLUMNS => [
    ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
    ['name' => 'title', 'type' => 'string', 'length' => 255, 'notnull' => true],
    ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => false],
]
// For unique on email, add in MDK::INDEXES: ['columns' => ['email'], 'unique' => true]
```

**Example (rename):** `['name' => 'title', MDK::RENAME => 'name']`  
**Example (drop):** `['name' => 'legacy_field', MDK::DROP => true]`

**If the column has an index:** You must drop the index first, then rename the column, then add the index on the new name. The bundle applies `drop_indexes` before column renames, then adds new indexes, so you can do it in one definition. See [USAGE.md](USAGE.md#renaming-a-column-that-has-an-index).

**Reusing column definitions:** The bundle provides **`Nowo\MigrationsKitBundle\FieldDictionary\IdField`** for the standard `id` column: `IdField::column()` and `IdField::primaryKey()`. You can centralise other common columns (e.g. audit timestamps, created_by/updated_by) in a helper class. The demos provide **`migrations/FieldDictionary/AuditFields`** for this; see [demo/README.md](../demo/README.md#field-dictionary-migrationsfielddictionary) and [USAGE.md](USAGE.md#reusable-audit-columns-field-dictionary).

### Supported types (DBAL)

`string`, `integer`, `smallint`, `bigint`, `boolean`, `decimal`, `float`, `text`, `datetime`, `datetime_immutable`, `date`, `time`, `json`, `blob`, `guid`, `ascii_string`, etc. See [Doctrine DBAL types](https://www.doctrine-project.org/projects/doctrine-dbal/en/stable/reference/types.html).

---

## Primary key

Array of associative arrays. Usually one element with **columns** (list of column names):

```php
MDK::PRIMARY_KEY => [['columns' => ['id']]]
```

Use **drop_primary_keys** (e.g. `[]`) in the table def to drop the primary key. To **change** the primary key on an existing table, define `PRIMARY_KEY` with the new columns; the bundle will drop the current PK and add the new one (via comparator). On SQLite this may not emit SQL due to platform limitations.

---

## Indexes

Array of associative arrays. Keys: **columns** (array of column names), **unique** (bool), **name** (optional). Use **drop: true** to remove an index by name.

```php
MDK::INDEXES => [
    ['columns' => ['email'], 'unique' => true],
    ['columns' => ['created_at'], 'name' => 'idx_created'],
]
```

**Shortcut:** `drop_indexes` => `['idx_old', 'idx_foo']` to drop by name.

**Renaming a column that is in an index:** Drop the index first, then rename the column, then add the index on the new column name. See [USAGE.md](USAGE.md#renaming-a-column-that-has-an-index).

---

## Foreign keys

Array of associative arrays. Keys: **columns** (local columns), **foreign_table**, **foreign_columns**, optional **name**, **onUpdate**, **onDelete**. Use **drop: true** to remove.

**onDelete** and **onUpdate** (e.g. `'CASCADE'`, `'SET NULL'`, `'RESTRICT'`) are passed through to the platform; the generated `ALTER TABLE ... ADD CONSTRAINT` SQL will include `ON DELETE` and `ON UPDATE` clauses on MySQL/MariaDB and other platforms that support them. Example: `'onDelete' => 'SET NULL'` produces `... REFERENCES parent (id) ON DELETE SET NULL`. Demo migration Version20250223100003 and its validation (Version20250223100003_validation) demonstrate and verify this.

**Constants:** Use **MigrationDefinitionKeys::ON_DELETE_*** and **ON_UPDATE_*** (e.g. `MDK::ON_DELETE_CASCADE`, `MDK::ON_DELETE_SET_NULL`, `MDK::ON_UPDATE_CASCADE`) instead of string literals for type safety and consistency. **MySQL (InnoDB):** CASCADE, SET NULL, RESTRICT and NO ACTION are supported and useful; SET DEFAULT is **not** supported by InnoDB—use SET NULL or RESTRICT instead.

```php
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

MDK::FOREIGN_KEYS => [
    [
        'columns'         => ['user_id'],
        'foreign_table'   => 'users',
        'foreign_columns' => ['id'],
        'onDelete'        => MDK::ON_DELETE_SET_NULL,
        'onUpdate'        => MDK::ON_UPDATE_CASCADE,
        'name'            => 'fk_my_table_user',
    ],
]
```

**Shortcut:** `drop_foreign_keys` => `['fk_table_user_id']` to drop by name. Use **SchemaNameGenerator::generateForeignKeyName($tableName, $columns)** to get deterministic names when reverting migrations.

---

## Using CreateTablesService in a migration

In a migration you **introspect the schema** and call **apply()** with the definition. The service returns the list of SQL statements; add each with `$this->addSql()`:

```php
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

// In up() or down():
$schema = $this->connection->createSchemaManager()->introspectSchema();
$service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
$definition = [ MDK::TABLES => [ ... ] ];

foreach ($service->apply($schema, $definition) as $sql) {
    $this->addSql($sql);
}
```

**Important:** Pass the **introspected** schema (`introspectSchema()`) so the service sees the current database state and only emits SQL for what is missing or changed.

---

## Full example

```php
$definition = [
    MDK::TABLES => [
        'users' => [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => true],
                ['name' => 'roles', 'type' => 'json', 'notnull' => true],
                ['name' => 'created_at', 'type' => 'datetime_immutable', 'notnull' => false],
            ],
            MDK::PRIMARY_KEY => [['columns' => ['id']]],
            MDK::INDEXES => [
                ['columns' => ['email'], 'unique' => true],
            ],
        ],
        'orders' => [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
                ['name' => 'total', 'type' => 'decimal', 'precision' => 10, 'scale' => 2, 'notnull' => true],
            ],
            MDK::PRIMARY_KEY => [['columns' => ['id']]],
            MDK::INDEXES => [['columns' => ['user_id']]],
            MDK::FOREIGN_KEYS => [
                ['columns' => ['user_id'], 'foreign_table' => 'users', 'foreign_columns' => ['id']],
            ],
        ],
    ],
];
```

---

## SchemaChecker (checks only)

For conditional logic without the full definition format, use **SchemaChecker**:

- `tableExists(string $tableName): bool`
- `columnExists(string $table, string $column): bool`
- `indexExists(string $table, string $indexName): bool`
- `hasPrimaryKey(string $tableName): bool`
- `foreignKeyExists(string $table, string $fkName): bool`
- `listTableColumns(string $table): array`
- `getConnection(): Connection`

See [USAGE.md](USAGE.md) for examples.

---

## See also

- [FLOWCHARTS.md](FLOWCHARTS.md) — Flow diagrams (Mermaid) for apply(), drop/create/edit paths, checks and DBAL API used.
- [EXAMPLE.md](EXAMPLE.md) — Minimal migration examples with `apply()` and interleaved `addSql()`.
- [DEMO_MIGRATIONS_REFERENCE.md](DEMO_MIGRATIONS_REFERENCE.md) — Use cases matrix, expected SQL per migration, safety.
- [CONFIGURATION.md](CONFIGURATION.md) — Bundle configuration.
- [USAGE.md](USAGE.md#reusable-audit-columns-field-dictionary) — Reusable audit columns (field dictionary).
- Demo migrations in `demo/symfony7`, `demo/symfony8` — runnable definitions and **FieldDictionary/AuditFields** for create, edit, rename, drop (columns, indexes, FKs, tables). See Version20250223100000–00013.
