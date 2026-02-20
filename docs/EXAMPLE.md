# Full example

This page shows a complete example: a migration that creates tables and columns using **SchemaChecker** and **MigrationDefinitionRunner**, and optionally inspects columns before adding new ones.

## Migration 1: Array-based definition (tables + columns)

Create a `config` table and a `user_settings` table, then add columns only when they do not exist:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

final class Version20250220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create config and user_settings tables with optional columns';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        $runner = new MigrationDefinitionRunner($checker);

        $runner->run([
            'tables' => [
                'config' => [
                    'create_sql' => 'CREATE TABLE config (
                        id INT AUTO_INCREMENT NOT NULL,
                        key_name VARCHAR(64) NOT NULL,
                        value LONGTEXT DEFAULT NULL,
                        PRIMARY KEY(id),
                        UNIQUE INDEX UNIQ_config_key (key_name)
                    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
                ],
                'user_settings' => [
                    'create_sql' => 'CREATE TABLE user_settings (
                        id INT AUTO_INCREMENT NOT NULL,
                        user_id INT NOT NULL,
                        theme VARCHAR(32) DEFAULT NULL,
                        PRIMARY KEY(id),
                        INDEX IDX_user_settings_user (user_id)
                    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
                ],
            ],
            'columns' => [
                ['table' => 'config', 'column' => 'updated_at', 'add_sql' => 'ALTER TABLE config ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
                ['table' => 'user_settings', 'column' => 'created_at', 'add_sql' => 'ALTER TABLE user_settings ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'],
            ],
        ], [$this, 'addSql']);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_settings');
        $this->addSql('DROP TABLE IF EXISTS config');
    }
}
```

Only missing tables and columns are created; existing ones are skipped.

## Migration 2: SchemaChecker only (conditional SQL)

Create a table only if it does not exist, and add a column to an existing table only if the column is missing:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

final class Version20250220110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feature_flags table and beta_enabled column on user';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);

        if (!$checker->tableExists('feature_flags')) {
            $this->addSql('CREATE TABLE feature_flags (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(64) NOT NULL,
                enabled TINYINT(1) DEFAULT 1 NOT NULL,
                PRIMARY KEY(id),
                UNIQUE INDEX UNIQ_feature_name (name)
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
        $this->addSql('DROP TABLE IF EXISTS feature_flags');
    }
}
```

## Migration 3: List columns and add only missing ones

Useful when the same table may have different columns per environment:

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

## What you get

- **Idempotent migrations:** Safe to run multiple times; only missing objects are created.
- **Clear intent:** SchemaChecker methods make it obvious what is being checked.
- **Compatibility:** Works with Doctrine DBAL 2.x, 3.x, 4.x and doctrine/migrations 3.x, 4.x.

## See also

- [Usage](USAGE.md) — SchemaChecker, MigrationDefinitionRunner, multiple connections.
- [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) — SchemaSync and the declarative schema format.
- [Demo migrations](https://github.com/nowo-tech/migrations-kit-bundle/tree/main/demo) — runnable examples in `demo/symfony6`, `demo/symfony7`, `demo/symfony8`.
