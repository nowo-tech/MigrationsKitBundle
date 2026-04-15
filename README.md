# Migrations Kit Bundle

[![CI](https://github.com/nowo-tech/MigrationsKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/MigrationsKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/migrations-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/migrations-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207%20%7C%208-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/migrations-kit-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/MigrationsKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-99.39%25-brightgreen)](#tests-and-coverage)

**Symfony bundle that provides helpers for Doctrine Migrations**: schema checks (table/column/index exist) and array-based migration definitions, so you can write idempotent migrations without repeating SQL and with safe checks. For **Symfony 6, 7 or 8** · PHP 8.1+ · **Doctrine DBAL** 2.x–4.x and **doctrine/migrations** 3.x–4.x.

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/MigrationsKitBundle) so more developers can find it.

## Table of contents

- [Quick search terms](#quick-search-terms)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Documentation](#documentation)
- [Requirements](#requirements)
- [Demo](#demo)
- [Development](#development)
- [License & author](#license--author)

## Quick search terms

Looking for **Doctrine migrations helpers**, **table exists migration**, **column exists check**, **idempotent migrations**, **migration schema check**, **Symfony Doctrine Migrations**, **declarative schema migrations**? You're in the right place.

## Features

- ✅ **SchemaChecker** — `tableExists`, `columnExists`, `indexExists`, `hasPrimaryKey`, `foreignKeyExists`, `listTableColumns`; no container injection: `new SchemaChecker($this->connection)`
- ✅ **CreateTablesService** — apply declarative definition arrays (MDK format); create/drop tables, add/drop/rename/modify columns, add/drop indexes, foreign keys; call `apply($schema, $definition)` with introspected schema and add each returned SQL with `$this->addSql()`
- ✅ **MigrationDefinitionKeys (MDK)** — constants for definition keys (`tables`, `columns`, `primary_key`, `indexes`, `foreign_keys`, `drop_columns`, etc.); use alias **MDK** in code; see [DECLARATIVE_SCHEMA.md](docs/DECLARATIVE_SCHEMA.md)
- ✅ **Array of associative arrays** — columns, indexes, FKs defined as arrays of items (each with `name`/`columns`, `type`, `drop`, `rename`, etc.); no raw SQL for schema changes
- ✅ **Recommended practice** — use separate migrations: first add column, then add index, then add foreign key; when dropping, first drop indexes then drop columns (see [docs/USAGE.md](docs/USAGE.md#recommended-independent-well-ordered-migrations)). The bundle **always applies operations in the correct order** (drops: FK → index → column; adds: columns → PK → indexes → FKs), but phased migrations are more stable
- ✅ Compatible with **Doctrine DBAL 2.x, 3.x, 4.x** and **doctrine/migrations 3.x, 4.x**
- ✅ **SQLite, MySQL and PostgreSQL** — schema checks and migrations work with all three
- ✅ **Symfony Flex** recipe (register bundle + config; see [docs/INSTALLATION.md](docs/INSTALLATION.md))
- ✅ **Demos** for Symfony 7 and 8 with example migrations (create table, add/rename/drop columns, indexes, FKs); Make targets to view migration SQL (`migrate-verbose`, `migrate-dry-run`, `migrate-write-sql`)

## Installation

```bash
composer require nowo-tech/migrations-kit-bundle
```

[![Install from Packagist](https://img.shields.io/badge/Packagist-install-777BB4?logo=composer)](https://packagist.org/packages/nowo-tech/migrations-kit-bundle)

With **Symfony Flex**, the recipe (when enabled) registers the bundle and creates the config file automatically. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md) for manual steps.

**Manual registration** in `config/bundles.php`:

```php
<?php

return [
  // ...
  Nowo\MigrationsKitBundle\NowoMigrationsKitBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/nowo_migrations_kit.yaml` (optional; defaults to `connection: default`):

```yaml
nowo_migrations_kit:
  connection: default  # Doctrine connection name for CreateTablesService when registered as a service
```

Full options: [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Usage

In your migration, use the migration's connection — no service injection required:

**SchemaChecker** — run SQL only when something does not exist:

```php
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

$checker = new SchemaChecker($this->connection);
if (!$checker->tableExists('app_settings')) {
  $this->addSql('CREATE TABLE app_settings (...)');
}
if (!$checker->columnExists('app_settings', 'created_at')) {
  $this->addSql('ALTER TABLE app_settings ADD created_at DATETIME NOT NULL');
}
```

**CreateTablesService** — apply a declarative definition (MDK format); the service returns the list of SQL statements; add each with `$this->addSql()`:

```php
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

$schema = $this->connection->createSchemaManager()->introspectSchema();
$service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
$definition = [ MDK::TABLES => [ 'users' => [ MDK::COLUMNS => [ ... ], MDK::PRIMARY_KEY => [ ... ] ] ] ];
foreach ($service->apply($schema, $definition) as $sql) {
  $this->addSql($sql);
}
```

More examples: [docs/USAGE.md](docs/USAGE.md) and [docs/EXAMPLE.md](docs/EXAMPLE.md).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Roadmap](docs/ROADMAP.md)

### Additional documentation

- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)
- [Example](docs/EXAMPLE.md)
- [Declarative schema](docs/DECLARATIVE_SCHEMA.md)
- [Demo migrations reference](docs/DEMO_MIGRATIONS_REFERENCE.md)
- [Demo](demo/README.md)
- [Flow diagrams](docs/FLOWCHARTS.md)

## Requirements

- PHP >= 8.1
- **Symfony 6, 7 or 8** (^6.0 \|\| ^7.0 \|\| ^8.0)
- doctrine/doctrine-bundle ^2.8 \|\| ^3.0
- doctrine/dbal ^2.13 \|\| ^3.0 \|\| ^4.0
- doctrine/migrations ^3.5 \|\| ^4.0

**Databases:** the bundle is compatible with **SQLite**, **MySQL** and **PostgreSQL**. Use the same migrations and helpers; platform-specific SQL is handled by Doctrine DBAL.

See [docs/INSTALLATION.md](docs/INSTALLATION.md#requirements) and [docs/UPGRADING.md](docs/UPGRADING.md) for compatibility notes.

## Demo

Demos for Symfony 7 and 8 are in `demo/symfony7`, `demo/symfony8`. Each runs with **FrankenPHP** in Docker. With the default **`APP_ENV=dev`**, the entrypoint uses **`Caddyfile.dev`** (no PHP worker: one process per request). **Production-style** demos use the default Caddyfile with **worker mode** (see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md)). Each demo includes example migrations using **CreateTablesService** and the MDK format (create table, add/rename/modify/drop columns, indexes, foreign keys), plus a **field dictionary** (`migrations/FieldDictionary/AuditFields`) for reusable audit columns (timestamps, created_by/updated_by with FK in two phases). From the bundle root: `make demo-up-symfony8` then `make demo-migrate-symfony8`. **Always check SQL before applying:** use `make migrate-dry-run` (or `doctrine:migrations:migrate --dry-run -vvv`). See [docs/USAGE.md](docs/USAGE.md#viewing-sql-before-running-migrations) for all options (`migrate-dry-run`, `migrate-write-sql`, `migrate-verbose`). [demo/README.md](demo/README.md) and each `demo/symfony*/README.md` have run instructions.

## Development

Run tests and QA with Docker: `docker compose up -d --build && docker compose exec php composer install && docker compose exec php composer test` (or `composer test-coverage`, `composer qa`). Without Docker: `composer install && composer test`. See [Makefile](Makefile) for all targets.

## Tests and coverage

- Tests: PHPUnit (PHP)
- PHP: 99.39%
- TS/JS: N/A
- Python: N/A

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)
