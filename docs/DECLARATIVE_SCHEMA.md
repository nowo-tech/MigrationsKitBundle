# Declarative schema - Migrations Kit Bundle

This document describes the **declarative schema definition** format and the **SchemaSync** service. You describe the desired database schema in a single array; the toolkit compares it with the current DB and runs only the needed SQL (create/drop tables, add/drop/change columns, add/drop indexes).

**Requirements:** Doctrine DBAL 3.x or 4.x (for `introspectSchema` and `createComparator`).

---

## Definition format

Top-level key: `tables`. Each table has:

- **columns** (required): map of column name => column options
- **primary_key** (optional): array of column names
- **indexes** (optional): map of index name => index definition
- **options** (optional): table options (e.g. `charset`, `collate` for MySQL)

### Column options

| Option          | Description                    | Example                    |
|-----------------|--------------------------------|----------------------------|
| `type`          | DBAL type name (required)      | `string`, `integer`, `json`|
| `length`        | Length for string/decimal      | `180`                      |
| `precision`     | Precision for decimal          | `10`                       |
| `scale`         | Scale for decimal              | `2`                        |
| `notnull`       | Not null                       | `true`                     |
| `default`       | Default value                  | `null`, `0`, `CURRENT_TIMESTAMP` |
| `autoincrement` | Auto-increment                 | `true`                     |
| `comment`       | Column comment                 | `'User email'`            |
| `unsigned`      | Unsigned (integer)             | `true`                     |
| `fixed`         | Fixed-length string            | `false`                    |

### Supported types (DBAL type names)

`string`, `integer`, `smallint`, `bigint`, `boolean`, `decimal`, `float`, `text`, `datetime`, `datetime_immutable`, `date`, `time`, `json`, `blob`, `guid`, `ascii_string`, etc. See [Doctrine DBAL types](https://www.doctrine-project.org/projects/doctrine-dbal/en/stable/reference/types.html).

### Indexes

Each index can be:

- **Short form:** `'index_name' => ['columns' => ['col1', 'col2']]` or `'index_name' => ['col1', 'col2']`
- **With unique:** `'index_name' => ['columns' => ['email'], 'unique' => true]`

---

## Full example

```php
$definition = [
    'tables' => [
        'users' => [
            'columns' => [
                'id' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                'email' => ['type' => 'string', 'length' => 180, 'notnull' => true],
                'roles' => ['type' => 'json', 'notnull' => true],
                'password' => ['type' => 'string', 'length' => 255, 'notnull' => true],
                'created_at' => ['type' => 'datetime_immutable', 'notnull' => false],
            ],
            'primary_key' => ['id'],
            'indexes' => [
                'uniq_email' => ['columns' => ['email'], 'unique' => true],
            ],
            'options' => ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'],
        ],
        'orders' => [
            'columns' => [
                'id' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                'user_id' => ['type' => 'integer', 'notnull' => true],
                'total' => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'notnull' => true],
            ],
            'primary_key' => ['id'],
            'indexes' => [
                'idx_orders_user' => ['columns' => ['user_id']],
            ],
        ],
    ],
];
```

---

## Common column definitions (StandardColumns)

The bundle provides **StandardColumns** for reusable audit fields. Use them in declarative schema (SchemaSync) or to add columns/indexes in migrations (MigrationDefinitionRunner).

### Declarative (SchemaSync): merge into your table definition

```php
use Nowo\MigrationsKitBundle\Schema\StandardColumns;

$definition = [
    'tables' => [
        'my_table' => [
            'columns' => array_merge(
                ['id' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true], 'name' => ['type' => 'string', 'length' => 255]],
                StandardColumns::auditColumns()
            ),
            'primary_key' => ['id'],
            'indexes' => StandardColumns::auditIndexes(),
        ],
    ],
];
```

- **StandardColumns::timestampColumns(bool $nullable = true)** — `created_at`, `updated_at`
- **StandardColumns::userRefColumns(bool $nullable = true)** — `created_by`, `updated_by`
- **StandardColumns::auditColumns(bool $nullable = true)** — timestamps + user refs
- **StandardColumns::auditIndexes()** — `idx_created_by`, `idx_updated_by`

### Adding standard columns to an existing table (MigrationDefinitionRunner)

```php
$runner->run([
    'columns' => array_merge(
        StandardColumns::auditColumnSteps('my_table', $isSqlite),
        // other steps...
    ),
], $addSql);

foreach (StandardColumns::auditIndexSteps('my_table', $isSqlite) as $step) {
    $runner->ensureIndex($step['table'], $step['index'], $step['add_sql'], $addSql);
}
```

- **StandardColumns::timestampColumnSteps($table, $isSqlite)** — only created_at, updated_at
- **StandardColumns::userRefColumnSteps($table, $isSqlite)** — only created_by, updated_by
- **StandardColumns::auditIndexSteps($table, $isSqlite)** — for ensureIndex()

### Manual definitions (copy-paste)

If you prefer not to use the class: use created_at/updated_at as datetime_immutable, created_by/updated_by as integer, and indexes idx_created_by, idx_updated_by. Foreign keys: add in a separate migration.

--- `created_by`, `updated_by` (with index to user table)

```php
// Columns: integer, nullable (anonymous or system actions)
'created_by' => ['type' => 'integer', 'notnull' => false],
'updated_by' => ['type' => 'integer', 'notnull' => false],

// Indexes: for joins and lookups (replace user_id with your user table primary key column name if different)
'idx_created_by' => ['columns' => ['created_by']],
'idx_updated_by' => ['columns' => ['updated_by']],
```

### Full audit block (timestamps + user refs + indexes)

Copy into your table definition as needed:

```php
'columns' => [
    // ... your business columns ...
    'created_at' => ['type' => 'datetime_immutable', 'notnull' => false],
    'updated_at' => ['type' => 'datetime_immutable', 'notnull' => false],
    'created_by' => ['type' => 'integer', 'notnull' => false],
    'updated_by' => ['type' => 'integer', 'notnull' => false],
],
'indexes' => [
    // ... your other indexes ...
    'idx_created_by' => ['columns' => ['created_by']],
    'idx_updated_by' => ['columns' => ['updated_by']],
],
```

To add a **foreign key** to a `user` table (DBAL/DB dependent), add the constraint in a separate migration or via your platform’s ALTER TABLE after the table is created; the bundle’s declarative format focuses on columns and indexes. The index on `created_by` / `updated_by` is what you need for typical “index to another table” lookups and joins.

---

## Data steps (insert / update)

Besides schema (tables, columns), **MigrationDefinitionRunner::run()** accepts a **`data`** key to insert or update rows with optional checks. Use it to seed config, set defaults, or backfill values. The callable you pass must accept `(string $sql, array $params = [])` so parameterized SQL is used.

You can combine **tables**, **columns**, **indexes**, **rename_columns**, **modify_columns**, **drop_indexes**, **drop_columns** and **data** in the same `run()`. The runner runs steps in that order, so it is safe to create a table, add columns, add indexes, rename or modify columns, drop indexes/columns, and then insert or update rows in a single migration. See [USAGE.md](USAGE.md) for the full array format.

### Format

- **`data`**: array of steps. Each step is an array with exactly one of:
  - **`insert`**: `table`, `row` (column => value), and optional **`only_if_not_exists`** (column => value). If `only_if_not_exists` is set, the row is inserted only when no row matches those conditions.
  - **`update`**: `table`, `set` (column => value), `where` (column => value), and optional **`only_if_exists`** (bool). If `only_if_exists` is true, the update runs only when a row matching `where` exists.

### Example: seed app settings

```php
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

$checker = new SchemaChecker($this->connection);
$runner = new MigrationDefinitionRunner($checker);

$addSql = function (string $sql, array $params = []): void {
    $this->addSql($sql, $params);
};

$runner->run([
    'data' => [
        [
            'insert' => [
                'table' => 'app_settings',
                'row' => ['key_name' => 'app.version', 'value' => '1.0'],
                'only_if_not_exists' => ['key_name' => 'app.version'],
            ],
        ],
        [
            'update' => [
                'table' => 'app_settings',
                'set' => ['value' => '1.1'],
                'where' => ['key_name' => 'app.version'],
                'only_if_exists' => true,
            ],
        ],
    ],
], $addSql);
```

**SchemaChecker::rowExists()** is used for the checks; you can also call it directly in migrations for custom logic.

---

## SchemaSync usage

### In a migration

```php
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Schema\SchemaSync;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

public function up(Schema $schema): void
{
    $checker = new SchemaChecker($this->connection);
    $parser = new SchemaDefinitionParser();
    $sync = new SchemaSync($this->connection, $parser, $checker);

    $sync->sync([$this, 'addSql'], $definition);
}
```

### Dropping columns, indexes, primary key, and tables

The definition is the **desired state**. Whatever is **not** in the definition is dropped when you run `sync()`:

- **Columns**: omit a column from `columns` → it will be dropped (ALTER TABLE ... DROP COLUMN).
- **Indexes**: omit an index from `indexes` → it will be dropped.
- **Primary key**: change or omit `primary_key` → the comparator will generate the appropriate ALTER.
- **Tables**: omit a table from `tables` → it will be dropped **only if** you pass `['drop_tables' => true]` in options (by default tables that exist in DB but not in the definition are **not** dropped, for safety).

Example: to remove column `created_at` and index `idx_old`, update the definition so they are no longer present and run `$sync->sync([$this, 'addSql'], $definition)` again; the diff will drop them.

### Options

- **drop_tables** (default: `false`): if `true`, drop tables that exist in the DB but are not in the definition.
- Columns and indexes not in the definition are always dropped when altering the table (no extra option).

### Dry-run: get SQL without executing

```php
$sql = $sync->diff($definition);
foreach ($sql as $statement) {
    echo $statement . ";\n";
}

// With drop_tables
$sql = $sync->diff($definition, ['drop_tables' => true]);
```

---

## What SchemaSync does

1. **Parse** the definition into a Doctrine `Schema` (desired state).
2. **Introspect** the current database into a Doctrine `Schema` (current state).
3. **Compare** with Doctrine's `SchemaComparator` (platform-aware).
4. **Generate SQL** from the diff:
   - **New tables** → `CREATE TABLE`
   - **Modified tables** → `ALTER TABLE` (add/drop/change columns, add/drop indexes)
   - **Dropped tables** (only if `drop_tables` is true) → `DROP TABLE`

So you get: add/drop tables, add/drop/change columns (including type changes), add/drop indexes, and primary key handling, without writing raw SQL.

---

## SchemaLimitChecker (MySQL / MariaDB limits)

Before or after defining your schema, you can check that it does not exceed platform limits. **SchemaLimitChecker** validates the definition and returns (or emits) warnings for:

- **Max columns per table**: 1017 (InnoDB).
- **Max row size**: 65535 bytes (estimated from column types and lengths).
- **Max indexes per table**: 64.
- **Max columns per index**: 16.
- **Max index key length**: 3072 bytes (InnoDB utf8mb4; estimated from indexed string lengths).

Usage in a migration:

```php
use Nowo\MigrationsKitBundle\Schema\SchemaLimitChecker;

$limitChecker = new SchemaLimitChecker();
$platform = $this->connection->getDatabasePlatform()->getName();

// Option 1: get list of warnings
$warnings = $limitChecker->check($definition, $platform);
foreach ($warnings as $msg) {
    echo $msg . "\n";
}

// Option 2: trigger E_USER_WARNING for each (e.g. visible in console)
$limitChecker->warnIfOverLimits($definition, $platform);
```

Only runs for `mysql` and `maria` platforms; other platforms return no warnings.

---

## SchemaChecker: hasTable, hasColumn, hasPrimaryKey

For manual checks (without SchemaSync) you can use:

- `$checker->tableExists($tableName)`
- `$checker->columnExists($tableName, $columnName)`
- `$checker->indexExists($tableName, $indexName)`
- `$checker->hasPrimaryKey($tableName)`
- `$checker->foreignKeyExists($tableName, $fkName)`
- `$checker->listTableColumns($tableName)`
- `$checker->rowExists($tableName, $conditions)` — for data steps: check if a row matching the key-value map exists
- `$checker->getConnection()` — get the DBAL connection (e.g. for building parameterized SQL)

These work on DBAL 2.x, 3.x and 4.x and are used internally by the legacy `MigrationDefinitionRunner` and by your own migrations when you don’t use the declarative format.
