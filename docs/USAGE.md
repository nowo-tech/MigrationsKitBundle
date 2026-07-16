# Usage

This page describes how to use the bundle in Doctrine migrations. The main entry point for **creating tables and adding missing columns** is **CreateTablesService**: you pass an introspected schema and a definition (MDK format); the service checks whether each table and each column exists and emits only the needed SQL (CREATE TABLE or ALTER TABLE ADD COLUMN).

**Create tables and columns (current scope):**

| Service / util | Use |
|----------------|-----|
| **CreateTablesService** | `apply(Schema $schema, array $definition)` — creates tables that do not exist; for existing tables: renames columns (RENAME), modifies column type/options, adds missing columns; creates indexes/unique (INDEXES) and foreign keys. Uses introspected schema for checks. |
| **SchemaDefinitionParser** | Parses table definitions (MDK) into DBAL Table objects; `getColumnAddArgs(array $col)` and `getColumnOptions(array $col)` for column add/modify. |
| **MigrationDefinitionKeys (MDK)** | Constants: `TABLES`, `COLUMNS`, `PRIMARY_KEY`, `INDEXES`, `FOREIGN_KEYS`, `RENAME`, `DROP`, `DROP_TABLES`, `DROP_COLUMNS`, `DROP_INDEXES`, `DROP_FOREIGN_KEYS`, `DROP_PRIMARY_KEYS`. |

You typically introspect the schema and pass it to the service: `$schema = $this->connection->createSchemaManager()->introspectSchema();` then `foreach ($service->apply($schema, $definition) as $sql) { $this->addSql($sql); }`. See [MIGRATIONS_API.md](MIGRATIONS_API.md) and [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md). **Order of operations:** (1) Drop FKs/indexes, (2) Drop columns/PK/tables, (3) Create tables, rename/modify/add columns, (4) Create indexes, create FKs — see [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#order-of-operations-apply-execution-order).

**CreateTablesService behaviour:** If the table does not exist → CREATE TABLE with all defined columns and primary key. If the table exists → rename columns (RENAME), modify column type/options, add missing columns; then create indexes (INDEXES) and foreign keys. Column defs with `MDK::DROP => true` are skipped when adding; use `DROP_COLUMNS` to drop columns. Pass the **introspected** schema so it reflects the current database.

## Table of contents

- [SchemaChecker](#schemachecker)
  - [In the migration with `$this->connection`](#in-the-migration-with-thisconnection)
  - [Example: full migration using only SchemaChecker](#example-full-migration-using-only-schemachecker)
  - [Example: list columns and add only the missing ones](#example-list-columns-and-add-only-the-missing-ones)
  - [Available methods](#available-methods)
- [MigrationDefinitionKeys (MDK)](#migrationdefinitionkeys-mdk)
- [Recommended: independent, well-ordered migrations](#recommended-independent-well-ordered-migrations)
- [Renaming a column that has an index](#renaming-a-column-that-has-an-index)
- [CreateTablesService (declarative definitions)](#createtablesservice-declarative-definitions)
- [Reusable audit columns (field dictionary)](#reusable-audit-columns-field-dictionary)
- [Multiple connections](#multiple-connections)
- [Doctrine migrations versions](#doctrine-migrations-versions)
- [Viewing SQL before running migrations](#viewing-sql-before-running-migrations)
  - [Exporting and viewing SQL before applying](#exporting-and-viewing-sql-before-applying)
- [Executing and committing after each migration](#executing-and-committing-after-each-migration)
  - [Transaction already committed (deprecation) when using DDL on MySQL](#transaction-already-committed-deprecation-when-using-ddl-on-mysql)
  - [AbstractAsset::getName() deprecated (DBAL 5)](#abstractassetgetname-deprecated-dbal-5)
- [See also](#see-also)

## SchemaChecker

Service that checks whether tables, columns, indexes and foreign keys exist. Compatible with DBAL 2.x (deprecated methods) and 3.x/4.x.

### In the migration with `$this->connection`

You don't need to inject the service; the migration's connection is enough:

```php
use Doctrine\DBAL\Schema\Schema;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

final class Version20250219000001 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);

        if (!$checker->tableExists('app_settings')) {
            $this->addSql('CREATE TABLE app_settings (...)');
        }

        if (!$checker->columnExists('app_settings', 'key')) {
            $this->addSql('ALTER TABLE app_settings ADD key VARCHAR(64)');
        }

        if (!$checker->indexExists('users', 'idx_users_email')) {
            $this->addSql('CREATE INDEX idx_users_email ON users (email)');
        }

        if (!$checker->foreignKeyExists('orders', 'fk_orders_user')) {
            $this->addSql('ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)');
        }
    }
}
```

### Example: full migration using only SchemaChecker

Migration that creates a config table and adds a "feature flag" column to an existing table:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

final class Version20250219120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config table and beta_enabled column on user';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);

        if (!$checker->tableExists('config')) {
            $this->addSql('CREATE TABLE config (
                id INT AUTO_INCREMENT NOT NULL,
                key_name VARCHAR(64) NOT NULL,
                value LONGTEXT DEFAULT NULL,
                PRIMARY KEY(id),
                UNIQUE INDEX UNIQ_config_key (key_name)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if ($checker->tableExists('user') && !$checker->columnExists('user', 'beta_enabled')) {
            $this->addSql('ALTER TABLE user ADD beta_enabled TINYINT(1) DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if ($checker->columnExists('user', 'beta_enabled')) {
            $this->addSql('ALTER TABLE user DROP beta_enabled');
        }
        $this->addSql('DROP TABLE IF EXISTS config');
    }
}
```

### Example: list columns and add only the missing ones

Useful when a table may have different versions per environment:

```php
$checker = new SchemaChecker($this->connection);

if (!$checker->tableExists('invoice')) {
    $this->addSql('CREATE TABLE invoice (...)');
    return;
}

$columns = $checker->listTableColumns('invoice');
$wanted = ['tax_amount', 'discount_percent', 'notes'];

foreach ($wanted as $col) {
    if (!in_array($col, $columns, true)) {
        $this->addSql("ALTER TABLE invoice ADD {$col} ...");
    }
}
```

### Available methods

| Method | Description |
|--------|-------------|
| `tableExists(string $tableName): bool` | Whether the table exists |
| `columnExists(string $table, string $column): bool` | Whether the column exists on the table |
| `indexExists(string $table, string $indexName): bool` | Whether the index exists |
| `hasPrimaryKey(string $tableName): bool` | Whether the table has a primary key |
| `foreignKeyExists(string $table, string $fkName): bool` | Whether the foreign key exists |
| `listTableColumns(string $table): array` | List of column names for the table |
| `getConnection(): Connection` | Get the DBAL connection |
| `getSchemaManager(): AbstractSchemaManager` | Get the DBAL schema manager (for introspection) |

Table/column/index names are normalized (quotes stripped), so you can pass names with or without backticks depending on the driver.

---

## MigrationDefinitionKeys (MDK)

Use the **MigrationDefinitionKeys** constants (alias **MDK**) for definition arrays passed to **CreateTablesService::apply()**:

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
            MDK::INDEXES => [['columns' => ['email'], 'unique' => true]],
        ],
    ],
];
```

**Model:** `columns`, `primary_key`, `indexes`, and `foreign_keys` are **arrays of associative arrays**. Each element has a `name` or `columns` and optional `drop`, `rename`, or type/options. Shortcuts: `drop_columns`, `drop_indexes`, `drop_foreign_keys`, `drop_primary_keys` (arrays of names). See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) and the docblock in `MigrationDefinitionKeys.php` for the full structure.

---

## Recommended: independent, well-ordered migrations

**Order is handled by the bundle:** drops are always applied as FK → index → column; adds as columns → primary_key → indexes → foreign_keys. You can mix `drop_columns`, `drop_indexes`, add column + index + FK in one definition and the emitted SQL will be in a safe order.

**Even so, we recommend using separate migrations** when you add a column that will later have an index or foreign key:

1. **Migration 1:** Add the column only (e.g. `user_id`).
2. **Migration 2:** Add the index on that column.
3. **Migration 3:** Add the foreign key on that column.

When **dropping** columns that have indexes, prefer two phases: first migration drop the indexes, second migration drop the columns (same idea: one concern per migration, correct order guaranteed by the bundle if you do both in one).

Benefits:

- **Stable:** Each step is small and clear; fewer ordering or platform quirks.
- **Simple:** One concern per migration; easier to read and review.
- **Clear rollback:** Reverting one migration does one thing (e.g. drop FK only).
- **Less confusion:** No need to remember that “column first, then index, then FK” inside the same definition.

Example (recommended — 3 migrations):

```php
// Migration 1: add column only
$definition = [
    MDK::TABLES => [
        'orders' => [
            MDK::COLUMNS => [
                ['name' => 'user_id', 'type' => 'integer', 'notnull' => false],
            ],
        ],
    ],
];

// Migration 2: add index on user_id
$definition = [
    MDK::TABLES => [
        'orders' => [
            MDK::INDEXES => [
                ['columns' => ['user_id'], 'unique' => false],
            ],
        ],
    ],
];

// Migration 3: add foreign key on user_id
$definition = [
    MDK::TABLES => [
        'orders' => [
            MDK::FOREIGN_KEYS => [
                [
                    'columns' => ['user_id'],
                    'foreign_table' => 'users',
                    'foreign_columns' => ['id'],
                ],
            ],
        ],
    ],
];
```

You can still put column + index + FK in one migration if you prefer; the service will emit the SQL in the right order. The recommendation is for clarity and stability, not a technical limitation.

---

## Renaming a column that has an index

If you rename a column that **is part of an index** (or unique constraint), the index is tied to the old column name. Depending on the database, renaming the column may invalidate the index or the engine may update it automatically. To be **safe and portable**, you should:

1. **Drop the index** on the old column name.
2. **Rename the column** to the new name.
3. **Create the index** on the new column name.

The bundle applies operations in that order: first **drop_indexes** (and other drops), then **columns** (including renames), then **indexes** (adds). So you can do all three steps in **one migration** by listing them in the same definition:

```php
use Nowo\MigrationsKitBundle\Migration\SchemaNameGenerator;

$tableName = 'my_table';
$oldColumn = 'title';
$newColumn = 'name';
$idxName = SchemaNameGenerator::generateIndexName($tableName, [$oldColumn]);

$definition = [
    MDK::TABLES => [
        $tableName => [
            MDK::DROP_INDEXES => [$idxName],                    // 1) drop index on old column
            MDK::COLUMNS => [
                ['name' => $oldColumn, MDK::RENAME => $newColumn], // 2) rename column
            ],
            MDK::INDEXES => [
                ['columns' => [$newColumn], 'unique' => false],   // 3) add index on new column
            ],
        ],
    ],
];
```

**Order applied by the bundle:** Phase 1 runs first (so `drop_indexes` is applied), then column renames (Phase 3a), then new indexes are added (Phase 4a). So the emitted SQL order is correct.

**Alternative (separate migrations):** If you prefer one concern per migration, use three migrations: (1) drop the index, (2) rename the column, (3) add the index on the new name. Same idea when reverting (down): drop the new index, rename back, then add the old index.

**Same logic for unique constraints:** If the column has a unique index, drop that index (by name) before the rename, then add the unique index on the new column name.

---

## CreateTablesService (declarative definitions)

To drive migrations from a **declarative array** (tables, columns, indexes, foreign keys) and emit only the needed SQL, use **CreateTablesService**. You pass an **introspected** schema and a definition array (MDK format). The service uses the schema for all checks (table/column/index/FK existence).

- **Checks:** Use `$schema = $this->connection->createSchemaManager()->introspectSchema()` so the schema reflects the current database; then pass it to `$service->apply($schema, $definition)`.
- **When the table does not exist:** If the definition for that table has **only** column entries with `RENAME` (no full column types), the service does nothing (no CREATE TABLE and no renames). Otherwise it creates the table.
- **Apply order (enforced by the bundle):** Drops: FKs → indexes → columns → PK → tables. Adds: create tables → rename columns → modify columns → add columns → indexes → foreign keys.

- **apply(Schema $schema, array $definition): array**  
  Returns the list of SQL statements. Add each with `$this->addSql($sql)` in a loop.

Build the service with `new CreateTablesService($this->connection, new SchemaDefinitionParser())`.

See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) for the definition format, [FLOWCHARTS.md](FLOWCHARTS.md) for flow diagrams (checks and API used), and [EXAMPLE.md](EXAMPLE.md) for minimal migration examples.

---

## Reusable audit columns (field dictionary)

The bundle provides **`Nowo\MigrationsKitBundle\FieldDictionary\IdField`** for the standard primary key column: use **`IdField::column()`** in `MDK::COLUMNS` and **`IdField::primaryKey()`** in `MDK::PRIMARY_KEY`. The demos use it for the `id` column in their migrations.

To avoid repeating the same audit column definitions (timestamps, user references), you can use a **field dictionary** class in your project. The demos ship with **`migrations/FieldDictionary/AuditFields`** (namespace `DoctrineMigrations\FieldDictionary`), which provides:

- **Timestamps:** `AuditFields::createdAt()`, `AuditFields::updatedAt()`, `AuditFields::timestampColumns()` — use in `MDK::COLUMNS`.
- **User references** (created_by / updated_by), in two phases: **Phase 1** — add column only: `AuditFields::createdBy()`, `AuditFields::updatedBy()`, or `AuditFields::userRefColumns()` in `MDK::COLUMNS`. **Phase 2** — add foreign key: `AuditFields::createdByForeignKey($userTableName)`, `AuditFields::updatedByForeignKey($userTableName)`, or `AuditFields::userRefForeignKeys($userTableName)` in `MDK::FOREIGN_KEYS`.

Add to your app: create `migrations/FieldDictionary/AuditFields.php` (or copy from the demo) and register the namespace in `composer.json` autoload: `"DoctrineMigrations\\FieldDictionary\\": "migrations/FieldDictionary/"`. See [demo/README.md](../demo/README.md#field-dictionary-migrationsfielddictionary) for the full API and usage.

---

## Multiple connections

For a connection other than the default, instantiate **SchemaChecker** with that connection:

```php
$otherConnection = $this->registry->getConnection('other');
$checker = new SchemaChecker($otherConnection);
```

The bundle does **not** register **SchemaChecker** as a container service; use `new SchemaChecker($this->connection)` (or `new SchemaChecker($otherConnection)`) in migrations. The **`nowo_migrations_kit.connection`** option applies only to **CreateTablesService** when you register that class as a service (see [CONFIGURATION.md](CONFIGURATION.md)).

---

## Doctrine migrations versions

- **3.x:** namespace and paths are configured in `doctrine_migrations.migrations_paths`; default table is `doctrine_migration_versions`.
- **4.x:** same idea; the bundle remains compatible.

The kit uses only the connection available in `AbstractMigration` and the `addSql` method, so it works the same on 3.x and 4.x.

---

## Viewing SQL before running migrations

**We recommend always checking the generated SQL before applying migrations** (e.g. in CI or production). The most direct way is **`--dry-run -vvv`** (or **`make migrate-dry-run`** in the demos): it shows the SQL that would run without applying any changes.

### Exporting and viewing SQL before applying

**1. Export pending SQL to a file (validate before applying)**

Write all SQL that would be executed for pending migrations to a file. No migration is run; the database is unchanged.

```bash
# From your project root (or from demo/symfony8)
php bin/console doctrine:migrations:migrate latest --write-sql=var/migration.sql --no-interaction
```

Then open `var/migration.sql` in an editor to review or diff. In the demos you can use:

```bash
make migrate-write-sql
# SQL is written to var/migration.sql (if there are pending migrations)
```

**2. View SQL in the console (dry-run)**

See the SQL in the terminal without executing it:

```bash
php bin/console doctrine:migrations:migrate --dry-run --no-interaction -vvv
```

- **`-vv`** — migration names and a short summary.
- **`-vvv`** — **debug**: each executed SQL statement is printed (recommended for validation).

In the demos: `make migrate-dry-run` (uses `-vv`; add `-vvv` in the Makefile or run the command above manually for full SQL).

**3. Make console output easy to read**

- **Only the SQL lines:** filter debug output so only the generated statements are shown:

  ```bash
  php bin/console doctrine:migrations:migrate --dry-run --no-interaction -vvv 2>&1 | grep -E '^\[debug\]'
  ```

  Each line will look like `[debug] CREATE TABLE ...`; strip the `[debug]` prefix if you want (e.g. with `sed`).

- **One statement per line in a file:** after exporting to `var/migration.sql`, you can format or inspect with your editor. Doctrine writes one statement per line (or multi-line statements); use your IDE’s SQL formatter if needed.

- **Run and see SQL as it runs:** to see SQL at the moment it is executed (not for validation, but for auditing):

  ```bash
  php bin/console doctrine:migrations:migrate --no-interaction -vvv
  ```
  Or in the demos: `make migrate-verbose`.

| Objective | Command |
|-----------|--------|
| **View SQL without executing** | `doctrine:migrations:migrate --dry-run -vvv` or `make migrate-dry-run` |
| **Save SQL to file** | `doctrine:migrations:migrate latest --write-sql=var/migration.sql --no-interaction` or `make migrate-write-sql` |
| **View SQL while executing** | `doctrine:migrations:migrate -vvv` or `make migrate-verbose` |

Use `--dry-run -vvv` (or `make migrate-dry-run`) to review pending migrations before running them.

---

## Executing and committing after each migration

By default, **Doctrine Migrations runs each migration in its own transaction and commits after that migration**. So when you run `doctrine:migrations:migrate` with several pending migrations, the flow is: run migration 1 → commit → run migration 2 → commit → …

That changes if you use **all-or-nothing**:

- **`all_or_nothing: false`** (default in config) and **no `--all-or-nothing`** on the command → each migration executes and commits separately. This is what you want for “run SQL and commit after each migration”.
- **`all_or_nothing: true`** (config) or **`--all-or-nothing`** on the command → all pending migrations run in a single transaction; the commit happens only after the last one (or everything is rolled back on failure).

So to ensure **SQL is executed and committed after each migration**:

1. **Config** (`config/packages/doctrine_migrations.yaml`): do **not** set `all_or_nothing: true`. Omit it or set it to `false`.
2. **Command**: run migrate **without** `--all-or-nothing`:
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
   and **not**:
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
   ```

Each migration is still wrapped in a transaction by default (`transactional: true`). So for each migration: run its SQL → commit. With `all_or_nothing` off, those transactions are separate per migration.

### Transaction already committed (deprecation) when using DDL on MySQL

If you run migrations that perform **DDL on MySQL** (CREATE TABLE, ALTER TABLE, DROP TABLE, etc.), you may see this deprecation notice:

```text
User Deprecated: Context: trying to commit a transaction
Problem: the transaction is already committed, relying on silencing is deprecated.
Solution: override `AbstractMigration::isTransactional()` so that it returns false.
Automate that by setting `transactional` to false in the configuration.
```

**What it means:** Doctrine Migrations runs each migration inside a transaction. On MySQL, many DDL operations cause an **implicit commit**. By the time Migrations tries to commit at the end, the transaction is already closed, and the library currently “silences” that case. That behaviour is deprecated.

**What to do:** For migrations that run DDL on MySQL, disable the transaction so the warning goes away and behaviour matches Doctrine’s recommendation:

1. **Global config** — In `config/packages/doctrine_migrations.yaml`, set `transactional: false` so all migrations run without a wrapping transaction, or
2. **Per migration** — Override `isTransactional()` in the migration class so it returns `false` for those migrations that touch the schema:

   ```php
   public function isTransactional(): bool
   {
       return false;
   }
   ```

See [Doctrine’s explanation on implicit commits](https://www.doctrine-project.org/projects/doctrine-migrations/en/stable/explanation/implicit-commits.html) for details.

### AbstractAsset::getName() deprecated (DBAL 5)

Doctrine DBAL 4 deprecates `AbstractAsset::getName()` on schema objects (Table, Column, Index, ForeignKeyConstraint); it will be removed in DBAL 5.

**What it means:** If you call `$table->getName()`, `$column->getName()`, etc. in your migrations or helpers, you may see a deprecation notice. In DBAL 5 that method will no longer exist.

**What this bundle does:** The bundle does not call `getName()` directly on schema assets. It uses the internal helper `SchemaAssetName::get($asset)`, which:

- On **DBAL 3 and 4**: uses `getName()` when present (you may still see the deprecation from DBAL’s own code when it walks the schema to generate SQL).
- On **DBAL 5**: uses the new API (e.g. public property or replacement method) when `getName()` has been removed.

So the bundle is prepared for DBAL 5. If you write custom code that introspects the schema (e.g. looping over `$table->getColumns()` and reading the column name), prefer a DBAL-agnostic approach or the same pattern: try the new API first, fall back to `getName()` only when the method exists.

---

## See also

- [MIGRATIONS_API.md](MIGRATIONS_API.md) — summary of Symfony Migrations and DBAL API (checks, create, edit, delete; DBAL 3.x / 4.x differences)
- [CONFIGURATION.md](CONFIGURATION.md) — `nowo_migrations_kit.connection`
- [INSTALLATION.md](INSTALLATION.md) — requirements and registration
- [EXAMPLE.md](EXAMPLE.md) — migration examples with apply() and interleaved addSql()
- [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) — definition format and MDK
- [DEMO_MIGRATIONS_REFERENCE.md](DEMO_MIGRATIONS_REFERENCE.md) — use cases matrix, expected SQL per migration, safety
- [demo/README.md](../demo/README.md) — demos, Make targets, and **field dictionary** (AuditFields)
- Demo migrations in `demo/symfony8`
