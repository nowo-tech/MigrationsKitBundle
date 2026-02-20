# Migrations Kit Bundle

[![CI](https://github.com/nowo-tech/migrations-kit-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/migrations-kit-bundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/migrations-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/migrations-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207%20%7C%208-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/migrations-kit-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/migrations-kit-bundle)

**Symfony bundle that provides helpers for Doctrine Migrations**: schema checks (table/column/index exist) and array-based migration definitions, so you can write idempotent migrations without repeating SQL and with safe checks. For **Symfony 6, 7 and 8** · PHP 8.1+ · **Doctrine DBAL** 2.x–4.x and **doctrine/migrations** 3.x–4.x.

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/migrations-kit-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/migrations-kit-bundle) so more developers can find it.

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

Looking for **Doctrine migrations helpers**, **table exists migration**, **column exists check**, **idempotent migrations**, **migration schema check**, **Symfony Doctrine Migrations**, **MigrationDefinitionRunner**, **SchemaSync**, **declarative schema migrations**? You're in the right place.

## Features

- ✅ **SchemaChecker** — `tableExists`, `columnExists`, `indexExists`, `hasPrimaryKey`, `foreignKeyExists`, `listTableColumns`; no container injection: `new SchemaChecker($this->connection)`
- ✅ **MigrationDefinitionRunner** — run from an array (`tables`, `columns`, and `data` for insert/update steps with `only_if_not_exists` / `only_if_exists`); only executes SQL when the target does not exist; `ensureTable`, `ensureColumn`, `ensureIndex`
- ✅ **StandardColumns** — reusable audit columns and indexes (`created_at`, `updated_at`, `created_by`, `updated_by`); use with SchemaSync or MigrationDefinitionRunner
- ✅ **Declarative schema (SchemaSync)** — describe the desired schema in one array; create/drop tables, add/drop/change columns and indexes; requires DBAL 3.x or 4.x
- ✅ Compatible with **Doctrine DBAL 2.x, 3.x, 4.x** and **doctrine/migrations 3.x, 4.x**
- ✅ **SQLite, MySQL and PostgreSQL** — schema checks and migrations work with all three
- ✅ **Symfony Flex** recipe (register bundle + config; see [docs/INSTALLATION.md](docs/INSTALLATION.md))
- ✅ **Demos** for Symfony 6, 7 and 8 with 7 example migrations (array-based, SchemaChecker, ensureTable/ensureColumn/ensureIndex, listTableColumns, SchemaSync, data steps, StandardColumns); Make targets to view migration SQL (`migrate-verbose`, `migrate-dry-run`, `migrate-write-sql`)

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
    connection: default   # Doctrine connection for the injected SchemaChecker service
```

Full options: [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Usage

In your migration, use the migration's connection — no service injection required:

```php
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;

$checker = new SchemaChecker($this->connection);
$runner = new MigrationDefinitionRunner($checker);
```

**SchemaChecker** — run SQL only when something does not exist:

```php
if (!$checker->tableExists('app_settings')) {
    $this->addSql('CREATE TABLE app_settings (...)');
}
if (!$checker->columnExists('app_settings', 'created_at')) {
    $this->addSql('ALTER TABLE app_settings ADD created_at DATETIME NOT NULL');
}
```

**MigrationDefinitionRunner** — define tables and columns in an array; only missing ones are created. You can use the **MigrationDefinition** type for a typed definition (tables, columns, indexes, rename_columns, modify_columns, drop_indexes, drop_columns, data) and call `$def->run($runner, $addSql)` in each migration.

```php
$runner->run([
    'tables' => [
        'users' => ['create_sql' => 'CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) ...'],
    ],
    'columns' => [
        ['table' => 'users', 'column' => 'email', 'add_sql' => 'ALTER TABLE users ADD email VARCHAR(180) NOT NULL'],
    ],
], [$this, 'addSql']);
```

For **SchemaSync** (declarative schema) and more examples, see [docs/USAGE.md](docs/USAGE.md) and [docs/EXAMPLE.md](docs/EXAMPLE.md).

## Documentation

| Document | Description |
|----------|-------------|
| [**Installation**](docs/INSTALLATION.md) | Requirements, Flex and manual install |
| [**Configuration**](docs/CONFIGURATION.md) | All options and defaults |
| [**Usage**](docs/USAGE.md) | SchemaChecker, MigrationDefinitionRunner, SchemaSync, multiple connections |
| [**Example**](docs/EXAMPLE.md) | Full migration examples |
| [**Declarative schema**](docs/DECLARATIVE_SCHEMA.md) | SchemaSync format and options |
| [**Changelog**](docs/CHANGELOG.md) | Version history |
| [**Upgrading**](docs/UPGRADING.md) | Upgrade notes between versions |
| [**Roadmap**](docs/ROADMAP.md) | Vision and future ideas |
| [**Contributing**](docs/CONTRIBUTING.md) | How to contribute and code style |
| [**Release**](docs/RELEASE.md) | Release checklist (for maintainers) |

## Requirements

- PHP >= 8.1
- **Symfony 6, 7 or 8** (^6.0 \|\| ^7.0 \|\| ^8.0)
- doctrine/doctrine-bundle ^2.8 \|\| ^3.0
- doctrine/dbal ^2.13 \|\| ^3.0 \|\| ^4.0
- doctrine/migrations ^3.5 \|\| ^4.0

**Databases:** the bundle is compatible with **SQLite**, **MySQL** and **PostgreSQL**. Use the same migrations and helpers; platform-specific SQL is handled by Doctrine DBAL.

**SchemaSync** (declarative schema) requires DBAL 3.x or 4.x.

See [docs/INSTALLATION.md](docs/INSTALLATION.md#requirements) and [docs/UPGRADING.md](docs/UPGRADING.md) for compatibility notes.

## Demo

Demos for Symfony 6, 7 and 8 are in `demo/symfony6`, `demo/symfony7`, `demo/symfony8`. Each includes **7 example migrations** (array-based definition, SchemaChecker, ensureTable/ensureColumn/ensureIndex, listTableColumns, SchemaSync, data steps, StandardColumns). From the bundle root: `make demo-up-symfony8` then `make demo-migrate-symfony8`. Use `make migrate-verbose`, `make migrate-dry-run`, or `make migrate-write-sql` inside a demo to view migration SQL. See [demo/README.md](demo/README.md) and each `demo/symfony*/README.md` for run instructions.

## Development

Run tests and QA with Docker: `docker compose up -d --build && docker compose exec php composer install && docker compose exec php composer test` (or `composer test-coverage`, `composer qa`). Without Docker: `composer install && composer test`. See [Makefile](Makefile) for all targets.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)
