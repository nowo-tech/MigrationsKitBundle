# Installation

This guide covers installing Migrations Kit Bundle in a Symfony application.

## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Manual registration](#manual-registration)
- [Using in migrations](#using-in-migrations)
- [Next steps](#next-steps)

## Requirements

The bundle is **compatible with Symfony 7 and 8** (and PHP 8.2+).

- **PHP** >= 8.2
- **Symfony** ^7.0 || ^8.0
- **doctrine/doctrine-bundle** ^2.8 || ^3.0
- **doctrine/dbal** ^2.13 || ^3.0 || ^4.0
- **doctrine/migrations** ^3.5 || ^4.0

**Databases:** the bundle works with **SQLite**, **MySQL** and **PostgreSQL**. SchemaChecker and CreateTablesService use Doctrine DBAL, which generates the appropriate SQL for each platform.

## Install with Composer

```bash
composer require nowo-tech/migrations-kit-bundle
```

Use a constraint such as `^1.0` to stay on the current major version.

## Register the bundle

### With Symfony Flex

If you use Symfony Flex and the bundle is installed from Packagist, the recipe (once merged in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib)) will register the bundle and create `config/packages/nowo_migrations_kit.yaml` automatically. The recipe source is in the bundle repo under `.symfony/recipe/`. Until the recipe is on the Flex server, register the bundle and config manually as below.

### Manual registration

1. **Register the bundle** in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\MigrationsKitBundle\NowoMigrationsKitBundle::class => ['all' => true],
];
```

2. **Create configuration** (optional). Create `config/packages/nowo_migrations_kit.yaml`:

```yaml
nowo_migrations_kit:
    connection: default   # Doctrine connection for CreateTablesService when injected from the container
```

If the file is omitted, the bundle uses `connection: default`. See [Configuration](CONFIGURATION.md) for details.

## Using in migrations

No extra configuration is required. In your migration classes (`AbstractMigration`) you have `$this->connection`. Use **CreateTablesService** with an **introspected** schema:

```php
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

$schema = $this->connection->createSchemaManager()->introspectSchema();
$service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
foreach ($service->apply($schema, $definition) as $sql) {
    $this->addSql($sql);
}
```

If you inject **CreateTablesService** from the container (e.g. via a custom migration factory), it will use the `connection` configured here. See [CONFIGURATION.md](CONFIGURATION.md).

## Next steps

- [Configuration](CONFIGURATION.md) — connection option.
- [Usage](USAGE.md) — SchemaChecker, CreateTablesService, MDK, multiple connections.
- [Example](EXAMPLE.md) — full migration examples.
- [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) — declarative definition format (MDK) and apply().
- [DEMO_MIGRATIONS_REFERENCE.md](DEMO_MIGRATIONS_REFERENCE.md) — use cases matrix, expected SQL per migration, safety.
