# Full example

For runnable examples see the [demo migrations](https://github.com/nowo-tech/MigrationsKitBundle/tree/main/demo) in `demo/symfony7` and `demo/symfony8`.

## Table of contents

- [Basic usage: apply returns SQL, you add it](#basic-usage-apply-returns-sql-you-add-it)
- [Interleaving: apply + manual addSql + apply again](#interleaving-apply--manual-addsql--apply-again)
- [SchemaChecker only (conditional SQL)](#schemachecker-only-conditional-sql)
- [What you get](#what-you-get)
- [See also](#see-also)

## Basic usage: apply returns SQL, you add it

Call `apply($schema, $definition)` with an **introspected** schema. The service returns the list of SQL statements; add each with `$this->addSql()`:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\MyEntity;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

final class Version20250223100000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $definition = [
            MDK::TABLES => [
                MyEntity::TABLE_NAME => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'name', 'type' => 'string', 'length' => 255, 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $definition = [
            MDK::DROP_TABLES => [MyEntity::TABLE_NAME],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }
}
```

## Interleaving: apply + manual addSql + apply again

You can interleave multiple `apply()` calls with manual `$this->addSql()`:

```php
public function up(Schema $schema): void
{
    $introspected = $this->connection->createSchemaManager()->introspectSchema();
    $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());

    foreach ($service->apply($introspected, $definition1) as $sql) {
        $this->addSql($sql);
    }
    $this->addSql('-- custom raw SQL or comment');
    foreach ($service->apply($introspected, $definition2) as $sql) {
        $this->addSql($sql);
    }
}
```

## SchemaChecker only (conditional SQL)

For one-off checks without the full definition format:

```php
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

$checker = new SchemaChecker($this->connection);

if (!$checker->tableExists('feature_flags')) {
    $this->addSql('CREATE TABLE feature_flags (
        id INT AUTO_INCREMENT NOT NULL,
        name VARCHAR(64) NOT NULL,
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
}

if ($checker->tableExists('user') && !$checker->columnExists('user', 'beta_enabled')) {
    $this->addSql('ALTER TABLE user ADD beta_enabled TINYINT(1) DEFAULT 0 NOT NULL');
}
```

## What you get

- **Declarative definitions:** Tables, columns, indexes, foreign keys as arrays (see [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) and `MigrationDefinitionKeys`).
- **Idempotent migrations:** The service only emits SQL for what is missing or changed.
- **Flexible usage:** Use the emitter for one-shot apply, or collect SQL and interleave with manual `addSql()`.
- **Reusable audit columns:** For common columns (created_at, updated_at, created_by, updated_by), see the demo's [field dictionary](../demo/README.md#field-dictionary-migrationsfielddictionary) (`migrations/FieldDictionary/AuditFields`).

## See also

- [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) — Definition format and keys.
- [FLOWCHARTS.md](FLOWCHARTS.md) — Flow diagrams (Mermaid) for apply() and checks.
- [CONFIGURATION.md](CONFIGURATION.md) — Bundle configuration.
- [USAGE.md](USAGE.md#reusable-audit-columns-field-dictionary) — Field dictionary and reusable audit columns.
- Demo migrations in `demo/symfony7`, `demo/symfony8` — runnable examples and FieldDictionary.
