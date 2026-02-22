# Usage

This page collects usage examples of the bundle in Doctrine migrations. You can use **SchemaChecker** to check if tables, columns, indexes or foreign keys exist; **MigrationDefinitionRunner** to run SQL from an array only when the target does not exist; and **SchemaSync** (declarative schema) to drive create/alter/drop from a single definition. In migrations you typically use `new SchemaChecker($this->connection)` and `new MigrationDefinitionRunner($checker)` — no service injection required.

## SchemaChecker

Service that checks whether tables, columns, indexes and foreign keys exist. Compatible with DBAL 2.x (deprecated methods) and 3.x/4.x.

### In the migration with `$this->connection`

You don't need to inject the service; the migration's connection is enough:

```php
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
| `withConnection(Connection $connection): self` | Create a checker for another connection |

Table/column/index names are normalized (quotes stripped), so you can pass names with or without backticks depending on the driver.

---

## MigrationDefinitionKeys (standard keys)

To unify and avoid typos in migration definition keys, the bundle provides **MigrationDefinitionKeys**, a constants class in the style of Doctrine Types. Use it instead of string literals. You can import it with the **MDK** alias for shorter code:

```php
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;

$runner->run([
    MDK::TABLES => [
        'users' => ['create_sql' => 'CREATE TABLE users (...)'],
    ],
    MDK::COLUMNS => [
        ['table' => 'users', 'column' => 'email', 'add_sql' => '...'],
    ],
    MDK::DATA => [
        [MDK::INSERT => ['table' => 'config', 'row' => [...]]],
        [MDK::UPDATE => ['table' => 'config', 'set' => [...], 'where' => [...]]],
    ],
], $addSql);
```

**Available constants:** `TABLES`, `COLUMNS`, `INDEXES`, `RENAME_COLUMNS`, `MODIFY_COLUMNS`, `DROP_INDEXES`, `DROP_COLUMNS`, `DATA`, `INSERT`, `UPDATE`, `PRIMARY_KEY`. To list all top-level keys: `MDK::allTopLevel()`.

---

## MigrationDefinitionRunner

Runs a definition from an array and calls `addSql` only when needed (table or column does not exist).

### Array format

- **tables**: associative `[ table_name => [ 'create_sql' => '...' ] ]`. `create_sql` is run only if the table does not exist.
- **columns**: array of `[ 'table' => '...', 'column' => '...', 'add_sql' => '...' ]`. `add_sql` is run only if the column does not exist.
- **indexes**: array of `[ 'table' => '...', 'index_name' => '...', 'add_sql' => '...' ]`. `add_sql` is run only if the index does not exist.
- **rename_columns**: array of `[ 'table' => '...', 'old_name' => '...', 'new_name' => '...', 'rename_sql' => '...' ]`. `rename_sql` is run only if the column `old_name` exists.
- **modify_columns**: array of `[ 'table' => '...', 'column' => '...', 'modify_sql' => '...' ]`. `modify_sql` (e.g. `ALTER TABLE t MODIFY col ...`) is run only if the column exists.
- **drop_indexes**: array of `[ 'table' => '...', 'index_name' => '...', 'drop_sql' => '...' ]`. `drop_sql` is run only if the index exists.
- **drop_columns**: array of `[ 'table' => '...', 'column' => '...', 'drop_sql' => '...' ]`. `drop_sql` is run only if the column exists.
- **data**: array of insert/update steps (see [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#data-steps)).

Steps run in order: tables → columns → indexes → rename_columns → modify_columns → drop_indexes → drop_columns → data.

### MigrationDefinition (typed)

Use the **MigrationDefinition** type to build the definition with type-safe properties and call `run()` in your migration:

```php
use Nowo\MigrationsKitBundle\Migration\MigrationDefinition;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

$checker = new SchemaChecker($this->connection);
$runner = new MigrationDefinitionRunner($checker);
$addSql = fn (string $sql, array $params = []): void => $this->addSql($sql, $params);

$def = new MigrationDefinition(
    tables: [
        'users' => ['create_sql' => 'CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255), PRIMARY KEY(id)) ...'],
    ],
    columns: [
        ['table' => 'users', 'column' => 'email', 'add_sql' => 'ALTER TABLE users ADD email VARCHAR(180)'],
    ],
    indexes: [
        ['table' => 'users', 'index_name' => 'idx_email', 'add_sql' => 'CREATE INDEX idx_email ON users (email)'],
    ],
);
$def->run($runner, $addSql);
```

Or from an array (e.g. when building the definition dynamically):

```php
$def = MigrationDefinition::fromArray([
    'tables' => [...],
    'columns' => [...],
    'modify_columns' => [...],
]);
$def->run($runner, $addSql);
```

Properties: `tables`, `columns`, `indexes`, `renameColumns`, `modifyColumns`, `dropIndexes`, `dropColumns`, `data`. Only non-empty keys are passed to the runner.

### Full example

```php
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

$checker = new SchemaChecker($this->connection);
$runner = new MigrationDefinitionRunner($checker);

$runner->run([
    'tables' => [
        'users' => [
            'create_sql' => 'CREATE TABLE users (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
        ],
        'roles' => [
            'create_sql' => 'CREATE TABLE roles (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64), PRIMARY KEY(id)) ...',
        ],
    ],
    'columns' => [
        ['table' => 'users', 'column' => 'email', 'add_sql' => 'ALTER TABLE users ADD email VARCHAR(180)'],
        ['table' => 'users', 'column' => 'created_at', 'add_sql' => 'ALTER TABLE users ADD created_at DATETIME NOT NULL'],
    ],
], [$this, 'addSql']);
```

### Direct methods

If you prefer not to use the array:

```php
$addSql = fn (string $sql): void => $this->addSql($sql);

// Add only if not exists
$runner->ensureTable('users', $createUsersSql, $addSql);
$runner->ensureColumn('users', 'email', 'ALTER TABLE users ADD email VARCHAR(180)', $addSql);
$runner->ensureIndex('users', 'idx_email', 'CREATE INDEX idx_email ON users (email)', $addSql);
$runner->ensureForeignKey('orders', 'fk_orders_user', 'ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)', $addSql);

// Modify / drop only if exists
$runner->modifyColumn('users', 'email', 'ALTER TABLE users MODIFY email VARCHAR(255) DEFAULT NULL', $addSql);
$runner->dropColumn('users', 'legacy_flag', 'ALTER TABLE users DROP COLUMN legacy_flag', $addSql);
$runner->dropIndex('users', 'idx_old', 'ALTER TABLE users DROP INDEX idx_old', $addSql);
```

### Example: idempotent migration with array and MySQL/SQLite support

If your project may use MySQL or SQLite (e.g. in tests), you can choose the SQL based on the driver:

```php
public function up(Schema $schema): void
{
    $checker = new SchemaChecker($this->connection);
    $runner = new MigrationDefinitionRunner($checker);
    $platform = $this->connection->getDatabasePlatform()->getName();
    $isSqlite = str_contains(strtolower($platform), 'sqlite');

    $runner->run([
        'tables' => [
            'cache_pool' => [
                'create_sql' => $isSqlite
                    ? 'CREATE TABLE cache_pool (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, key_name VARCHAR(64) NOT NULL, value BLOB, expires_at INTEGER)'
                    : 'CREATE TABLE cache_pool (id INT AUTO_INCREMENT NOT NULL, key_name VARCHAR(64) NOT NULL, value LONGBLOB, expires_at INT, PRIMARY KEY(id), UNIQUE INDEX UNIQ_cache_key (key_name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
            ],
        ],
        'columns' => [
            [
                'table' => 'cache_pool',
                'column' => 'tags',
                'add_sql' => $isSqlite
                    ? 'ALTER TABLE cache_pool ADD COLUMN tags CLOB DEFAULT NULL'
                    : 'ALTER TABLE cache_pool ADD tags JSON DEFAULT NULL',
            ],
        ],
    ], [$this, 'addSql']);
}
```

### Example: multiple tables and columns in a single migration

Defining everything in one array keeps the migration readable and easy to extend:

```php
$definition = [
    'tables' => [
        'blog_post' => [
            'create_sql' => 'CREATE TABLE blog_post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT, published_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
        ],
        'blog_comment' => [
            'create_sql' => 'CREATE TABLE blog_comment (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, author VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id), INDEX IDX_post (post_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
        ],
    ],
    'columns' => [
        ['table' => 'blog_post', 'column' => 'slug', 'add_sql' => 'ALTER TABLE blog_post ADD slug VARCHAR(255) NOT NULL'],
        ['table' => 'blog_post', 'column' => 'view_count', 'add_sql' => 'ALTER TABLE blog_post ADD view_count INT DEFAULT 0 NOT NULL'],
        ['table' => 'blog_comment', 'column' => 'email', 'add_sql' => 'ALTER TABLE blog_comment ADD email VARCHAR(180) DEFAULT NULL'],
    ],
];

$runner->run($definition, [$this, 'addSql']);
```

### Example: modify, rename and drop (idempotent)

Use **modify_columns**, **rename_columns**, **drop_indexes** and **drop_columns** when aligning schema with entity changes (e.g. make column nullable, rename bucket → s3_bucket, drop old index):

```php
$runner->run([
    'rename_columns' => [
        ['table' => 'files', 'old_name' => 'bucket', 'new_name' => 's3_bucket', 'rename_sql' => 'ALTER TABLE files CHANGE bucket s3_bucket VARCHAR(255) DEFAULT NULL'],
    ],
    'modify_columns' => [
        ['table' => 'files', 'column' => 's3_access', 'modify_sql' => 'ALTER TABLE files MODIFY s3_access VARCHAR(255) DEFAULT NULL'],
    ],
    'drop_indexes' => [
        ['table' => 'files', 'index_name' => 'uniq_old', 'drop_sql' => 'ALTER TABLE files DROP INDEX uniq_old'],
    ],
    'indexes' => [
        ['table' => 'files', 'index_name' => 'uniq_s3_object_url', 'add_sql' => 'ALTER TABLE files ADD UNIQUE KEY uniq_s3_object_url (s3_object_url)'],
    ],
    'drop_columns' => [
        ['table' => 'documents', 'column' => 'aka', 'drop_sql' => 'ALTER TABLE documents DROP COLUMN aka'],
    ],
], fn (string $sql): void => $this->addSql($sql));
```

---

## Multiple connections

For a connection other than the default, use `withConnection`:

```php
$otherConnection = $this->registry->getConnection('other');
$checker = (new SchemaChecker($this->connection))->withConnection($otherConnection);
```

Example: checking a table on another connection (e.g. if your migration runs against the `legacy` connection and you want to inspect it; you would get the connection in a migration factory by injecting `ManagerRegistry`):

```php
// In a migration that receives the "legacy" connection, if you need to check the "default" connection:
// $defaultConnection = $registry->getConnection('default');
// $checkerDefault = (new SchemaChecker($this->connection))->withConnection($defaultConnection);
// if ($checkerDefault->tableExists('feature_flags')) { ... }
```

Or inject the `Nowo\MigrationsKitBundle\Migration\SchemaChecker` service (which uses the connection set in `nowo_migrations_kit.connection`) and, if you need another connection, create a checker with `$checker->withConnection($otherConnection)`.

---

## Declarative schema (SchemaSync)

To **standardize** migration creation and drive it from a single **descriptive array** (tables, columns with types, primary key, indexes), use **SchemaSync**. It compares the current DB with your definition and runs only the needed SQL: create/drop tables, add/drop/change columns, add/drop indexes. See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) for the array format and options. Requires DBAL 3+.

---

## Differences between doctrine/migrations versions

- **3.x**: namespace and paths are configured in `doctrine_migrations.migrations_paths`; default table is `doctrine_migration_versions`.
- **4.x**: same idea; the bundle remains compatible.

The kit does not rely on deprecated migration APIs; it only uses the connection you get in `AbstractMigration` and the `addSql` method, so it works the same on 3.x and 4.x.

## See also

- [Configuration](CONFIGURATION.md) — `nowo_migrations_kit.connection`
- [Installation](INSTALLATION.md) — requirements and registration
- [Example](EXAMPLE.md) — full migration examples
- [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) — SchemaSync and the declarative schema format
- [Demo migrations](https://github.com/nowo-tech/migrations-kit-bundle/tree/main/demo) — runnable demos for Symfony 6, 7 and 8
