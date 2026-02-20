# Installation

This guide covers installing Migrations Kit Bundle in a Symfony application.

## Requirements

The bundle is **compatible with Symfony 6, 7 and 8** (and PHP 8.1+).

- **PHP** >= 8.1
- **Symfony** ^6.0 || ^7.0 || ^8.0
- **doctrine/doctrine-bundle** ^2.8 || ^3.0
- **doctrine/dbal** ^2.13 || ^3.0 || ^4.0
- **doctrine/migrations** ^3.5 || ^4.0

**Databases:** the bundle works with **SQLite**, **MySQL** and **PostgreSQL**. SchemaChecker, MigrationDefinitionRunner and SchemaSync use Doctrine DBAL, which generates the appropriate SQL for each platform.

**SchemaSync** (declarative schema) requires DBAL 3.x or 4.x.

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
    connection: default   # Doctrine connection for the injected SchemaChecker service
```

If the file is omitted, the bundle uses `connection: default`. See [Configuration](CONFIGURATION.md) for details.

## Using in migrations

No extra configuration is required. In your migration classes (`AbstractMigration`) you have `$this->connection`; instantiate **SchemaChecker** and **MigrationDefinitionRunner** with it:

```php
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;

$checker = new SchemaChecker($this->connection);
$runner = new MigrationDefinitionRunner($checker);
```

If you prefer to inject the **SchemaChecker** service into migrations (e.g. to use the configured `connection`), configure a [custom migration factory](https://symfony.com/doc/current/bundles/DoctrineMigrationsBundle/index.html#custom-migration-factory).

## Next steps

- [Configuration](CONFIGURATION.md) — connection option.
- [Usage](USAGE.md) — SchemaChecker, MigrationDefinitionRunner, SchemaSync, multiple connections.
- [Example](EXAMPLE.md) — full migration examples.
- [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) — declarative schema format and SchemaSync.
