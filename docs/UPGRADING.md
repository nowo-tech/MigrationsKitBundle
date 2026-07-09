# Upgrade Guide

This guide explains how to upgrade Migrations Kit Bundle between versions. For a list of changes in each version, see [CHANGELOG.md](CHANGELOG.md).

**Current API:** The bundle provides **SchemaChecker** (table/column/index/FK checks, `listTableColumns`, `getConnection`, `getSchemaManager`) and **CreateTablesService** (declarative definitions in MDK format). Use **introspected** schema: `$schema = $this->connection->createSchemaManager()->introspectSchema()`, then call `$service->apply($schema, $definition)` and add each returned SQL with `$this->addSql($sql)` in a loop. Build the service with `new CreateTablesService($this->connection, new SchemaDefinitionParser())`. Supporting classes: **MigrationDefinitionKeys** (MDK constants), **SchemaDefinitionParser**. See [USAGE.md](USAGE.md) and [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md).

## Table of contents

- [General upgrade process](#general-upgrade-process)
- [Upgrading to 2.0.14](#upgrading-to-2014)
- [Upgrading to 2.0.13](#upgrading-to-2013)
- [Upgrading to 2.0.12](#upgrading-to-2012)
- [Upgrading to 2.0.11](#upgrading-to-2011)
- [Upgrading to 2.0.10](#upgrading-to-2010)
- [Upgrading to 2.0.9](#upgrading-to-209)
- [Upgrading to 2.0.8](#upgrading-to-208)
- [Upgrading to 2.0.7](#upgrading-to-207)
- [Upgrading to 2.0.6](#upgrading-to-206)
- [Upgrading to 2.0.5](#upgrading-to-205)
- [Upgrading to 2.0.4](#upgrading-to-204)
- [Upgrading to 2.0.3](#upgrading-to-203)
- [Upgrading to 2.0.2](#upgrading-to-202)
- [Upgrading to 2.0.1](#upgrading-to-201)
- [Upgrading to 2.0.0](#upgrading-to-200)
  - [What was removed](#what-was-removed)
  - [What stays the same](#what-stays-the-same)
  - [Upgrade steps](#upgrade-steps)
- [Upgrading to 1.2.1](#upgrading-to-121)
- [Upgrading to 1.2.0](#upgrading-to-120)
- [Upgrading to 1.1.0](#upgrading-to-110)
- [Upgrading to 1.0.0](#upgrading-to-100)

## General upgrade process

1. **Back up configuration**  
   Back up `config/packages/nowo_migrations_kit.yaml` (or wherever you configure the bundle) before upgrading.

2. **Check the changelog**  
   Review [CHANGELOG.md](CHANGELOG.md) for the target version to see new features, changes, and breaking changes.

3. **Update the package**  
   Run:
   ```bash
   composer update nowo-tech/migrations-kit-bundle
   ```

4. **Apply configuration and code changes**  
   If the new version introduces or changes config options or namespaces, update your config and PHP code (see version-specific sections below).

5. **Clear cache**  
   ```bash
   php bin/console cache:clear
   ```

6. **Test**  
   Run your migrations (e.g. in a test environment) to verify everything still works.

---

## Upgrading to 2.0.14

- **No breaking changes.** Runtime behaviour, migration SQL, and the public API are unchanged.
- **Maintainers** — GitHub Spec Kit baseline is documented in [SPEC-KIT.md](SPEC-KIT.md) and [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md). When you change production code under `src/`, keep `specs/001-baseline/` in sync.
- **Upgrade:** Run `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.13

- **No breaking changes.** Runtime behaviour, migration SQL, and the public API are unchanged.
- **Contributors** — From the bundle root you can run `make update-deps` to refresh Composer lock files in the bundle container and in both Symfony demos (REQ-MAKE-008). Existing targets `make update` and `make composer-sync` are unchanged.
- **Upgrade:** Run `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.12

- **No breaking changes.** This release fixes DBAL 3 CI jobs only (PHPUnit stub signatures); runtime behaviour and migration SQL are unchanged.
- **Upgrade:** Run `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.11

- **No breaking changes** for typical usage (`SchemaChecker`, `CreateTablesService`, MDK definitions). Generated migration SQL is unchanged.
- **Internal** — `TableSchemaHelper` is no longer declared `final`; you do not need to change migrations or config. This helper is not part of the documented public API.
- **Quality** — PHPStan level 8 clean; higher test coverage. Run `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.10

- **No breaking changes.** This release improves CI coverage (DBAL 3|4 × Symfony 7|8) and fixes PHPUnit compatibility with DBAL 3 in test stubs and MySQL drop-column assertions.
- **Upgrade:** Run `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed. No migration or config changes required.

---

## Upgrading to 2.0.9

- **No breaking changes.** This release removes **DBAL 4 deprecation notices** when the bundle sets primary keys or builds schema comparators during migration SQL generation.
- **Behaviour** — Generated SQL is unchanged; only internal calls were updated (`Table::addPrimaryKeyConstraint()` when available, `ComparatorConfig::withReportModifiedIndexes(false)` for comparators). Shared logic lives in the internal **TableSchemaHelper** class; you do not need to reference it in your migrations.
- **Upgrade:** Run `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed. If you run migrations with `-vvv` on DBAL 4, you should see fewer deprecation warnings from this bundle.

---

## Upgrading to 2.0.8

- **No breaking changes** for typical Symfony 7 / 8 and PHP 8.2+ projects.
- **Composer constraints** — The package now officially supports **PHP >= 8.1** and **Symfony 6** (`symfony/*` `^6.0 || ^7.0 || ^8.0`). If you are already on PHP 8.2+ and Symfony 7 or 8, behaviour is unchanged; you can adopt Symfony 6 or PHP 8.1 only if your stack requires it.
- **CreateTablesService** — When adding **foreign keys on new columns** in one `apply()`, errors on **non-SQLite** databases are no longer swallowed: only SQLite keeps the previous “skip quietly” behaviour for unsupported same-run FK creation. If you relied on silent failure on MySQL/PostgreSQL (unlikely), review migration logs.
- **Docs & recipe** — README, USAGE, DEMO-FRANKENPHP, and the Flex recipe comment align with **CreateTablesService** vs **SchemaChecker** and `nowo_migrations_kit.connection`.
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.7

- **No breaking changes.** This release focuses on internal quality (more tests and higher coverage, 0 PHPStan errors) and makes support for **Doctrine DBAL 3 and 4** explicit via dedicated CI jobs.
- **Demos:** The Symfony 7 demo adjusts migration `Version20250223100013` to skip the PK change on SQLite, avoiding driver errors in that environment.
- **Upgrade:** Run `composer update nowo-tech/migrations-kit-bundle` and validate with your own `make test` / `make test-coverage`.

---

## Upgrading to 2.0.6

- **No breaking changes.** This release fixes ON DELETE/ON UPDATE for **new tables** (CREATE TABLE with FKs) and avoids duplicate DROP COLUMN in generated SQL.
- **SchemaDefinitionParser:** When you define a table that **does not exist yet** with `MDK::FOREIGN_KEYS` and `onDelete`/`onUpdate`, the generated CREATE TABLE SQL now includes `ON DELETE` and `ON UPDATE` (previously only FKs added to existing tables via Phase 4 had options applied).
- **CreateTablesService:** If the platform/comparator returned the same DROP COLUMN (or other alter) statement twice in Phase 2a, the bundle now emits it only once.
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.5

- **No breaking changes.** This release standardises DROP FOREIGN KEY output and adds a demo target for generating MySQL SQL without executing.
- **CreateTablesService:** DROP FOREIGN KEY statements are now always generated in canonical form (no backticks), e.g. `ALTER TABLE customers DROP FOREIGN KEY FK_xxx`. If you compared or parsed generated SQL by string, the format is now consistent.
- **Demos:** New Make target `test-mysql-write-sql` writes migration SQL to `var/migration_mysql.sql` without running it (useful to inspect MySQL output). Run from `demo/symfony7` or `demo/symfony8`.
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.4

- **No breaking changes.** This release fixes foreign key options (onDelete/onUpdate) in generated SQL, avoids duplicate DROP FOREIGN KEY statements, and improves compatibility with Doctrine deprecations.
- **CreateTablesService:** FKs defined with `onDelete` and/or `onUpdate` in `MDK::FOREIGN_KEYS` now produce SQL that includes `ON DELETE` and `ON UPDATE` on MySQL/MariaDB and other supporting platforms. If you relied on default (e.g. RESTRICT) and did not specify these keys, behaviour is unchanged.
- **CreateTablesService:** When a table has both `DROP_FOREIGN_KEYS` and `DROP_COLUMNS` (the column being dropped is referenced by that FK), the generated SQL now contains a single `DROP FOREIGN KEY` for that constraint instead of two identical statements (the second would fail on MySQL).
- **Deprecations:** SchemaAssetName now uses reflection to read the asset name where possible, reducing the "AbstractAsset::getName is deprecated" notice. Drop-column logic normalizes column names so "Dropping columns referenced by constraints is deprecated" is avoided when dropping columns that have FKs.
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.3

- **No breaking changes.** This release adds documentation for Doctrine deprecations and prepares the bundle for DBAL 5.
- **Documentation:** USAGE.md now explains (1) the *"transaction already committed"* deprecation when running DDL on MySQL and how to fix it (`transactional: false` or `isTransactional() => false`), and (2) the `AbstractAsset::getName()` deprecation in DBAL 4/5 and that the bundle uses **SchemaAssetName::get()** for compatibility. DEMO_MIGRATIONS_REFERENCE and MIGRATIONS_API reference these sections.
- **Internal:** SchemaAssetName helper and tests were adjusted so the bundle is ready for DBAL 5 when `getName()` is removed.
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.2

- **No breaking changes.** New behaviour: when you add new columns and define indexes and/or foreign keys on those columns in the same MDK definition, **CreateTablesService::apply()** now emits ADD COLUMN, index and FK SQL in one run (no manual `addSql` for index/FK needed). See [DEMO_MIGRATIONS_REFERENCE.md](DEMO_MIGRATIONS_REFERENCE.md) and [CHANGELOG.md](CHANGELOG.md#202---2025-02-25).
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.1

- **No breaking changes.** Only documentation and release-process updates (RELEASE.md).
- Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache if needed.

---

## Upgrading to 2.0.0

**2.0.0 is a major release and is not backward compatible with 1.x.** If you rely on **MigrationDefinitionRunner**, **SchemaSync**, **StandardColumns**, **MigrationDefinition**, **data steps**, or **SchemaChecker::rowExists()**, you must migrate to the new API before upgrading.

### What was removed

| Removed | Replacement in 2.0 |
|--------|---------------------|
| `MigrationDefinitionRunner` and `run()` (tables, columns, indexes, data, ensureTable, ensureColumn, ensureIndex, etc.) | **CreateTablesService::apply($schema, $definition)** with an MDK-format array. Build the service with `new CreateTablesService($this->connection, new SchemaDefinitionParser())`. Loop over returned SQL and call `$this->addSql($sql)`. |
| `SchemaSync` (declarative sync from array) | Same: **CreateTablesService::apply($schema, $definition)**. Pass your migration’s `Schema` and the definition; the service returns the SQL to run. |
| `StandardColumns` (audit columns, timestamps, user refs) | Define columns directly in your MDK definition (e.g. under `MDK::COLUMNS` with `name`, `type`, `notnull`, etc.). No bundled helper class. |
| `MigrationDefinition` (typed value object) | Use plain PHP arrays and **MigrationDefinitionKeys (MDK)** constants. See [USAGE.md](USAGE.md) and [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md). |
| Data steps (`data` key, `only_if_not_exists`, `only_if_exists`) | Use raw `$this->addSql()` for INSERT/UPDATE, or implement your own checks (e.g. with `SchemaChecker::getConnection()` and a SELECT). |
| `SchemaChecker::rowExists()` | Run a SELECT via `$checker->getConnection()` or your repository when you need to check row existence. |
| Runner methods: `modifyColumn()`, `dropColumn()`, `dropIndex()`, `ensureForeignKey()` | Use MDK definition keys: `columns` (with `drop` or `rename`), `drop_columns`, `indexes` (with `drop`), `drop_indexes`, `foreign_keys` (with `drop`), `drop_foreign_keys`. See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md). |

### What stays the same

- **SchemaChecker** — `tableExists()`, `columnExists()`, `indexExists()`, `hasPrimaryKey()`, `foreignKeyExists()`, `listTableColumns()`, `getConnection()`, `getSchemaManager()`. Same usage: `new SchemaChecker($this->connection)` in migrations.
- **Configuration** — `nowo_migrations_kit.connection` (optional, default `default`). No change.
- **Bundle registration** — `NowoMigrationsKitBundle` in `config/bundles.php`. No change.

### Upgrade steps

1. **Replace MigrationDefinitionRunner / SchemaSync usage**  
   Convert each migration that used `MigrationDefinitionRunner::run()` or SchemaSync to use **CreateTablesService::apply()** with an MDK definition. Example:

   ```php
   use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
   use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
   use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

   $schema = $this->connection->createSchemaManager()->introspectSchema();
   $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
   $def = [ MDK::TABLES => [ 'my_table' => [ MDK::COLUMNS => [ ... ], MDK::PRIMARY_KEY => [ ... ] ] ] ];
   foreach ($service->apply($schema, $def) as $sql) {
       $this->addSql($sql);
   }
   ```

2. **Replace data steps**  
   Migrations that used the `data` key must be rewritten to use `$this->addSql()` (and optional checks with `getConnection()` or a query).

3. **Replace StandardColumns**  
   Inline the column definitions (e.g. `created_at`, `updated_at`) in your MDK `columns` arrays.

4. **Update Composer and clear cache**

   ```bash
   composer update nowo-tech/migrations-kit-bundle
   php bin/console cache:clear
   ```

5. **Run tests and migrations** (e.g. in a test environment) to confirm everything works.

---

## Upgrading to 1.2.1

- **Fixes:** DBAL 4 compatibility in SchemaSync (type names, altered tables, drop tables) and SchemaDefinitionParser; MigrationDefinitionRunner MDK import fix. No API or config changes.
- **Docs:** Documentation and in-code comments are now in English.
- **No breaking changes.** Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache.

---

## Upgrading to 1.2.0

- **New features:** Extended `MigrationDefinitionRunner::run()` with `indexes`, `rename_columns`, `modify_columns`, `drop_indexes`, `drop_columns`; new methods `modifyColumn()`, `dropColumn()`, `dropIndex()`, `ensureForeignKey()`; **SchemaCheckerInterface** for testing; **MigrationDefinition** typed value object. See [CHANGELOG.md](CHANGELOG.md#120---2026-02-20).
- **DI:** If you type-hint `SchemaCheckerInterface` in your code (e.g. custom migration factory), it resolves to the same `SchemaChecker` service. No config change needed.
- **No breaking changes.** Existing migrations using the array format or `SchemaChecker` continue to work. Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache.

---

## Upgrading to 1.1.0

- **New features:** Data steps in `MigrationDefinitionRunner::run()` (insert/update with `only_if_not_exists` / `only_if_exists`), `SchemaChecker::rowExists()` and `getConnection()`, and the **StandardColumns** class for audit fields. See [CHANGELOG.md](CHANGELOG.md#110---2026-02-20).
- **SchemaSync:** Fix for PostgreSQL when syncing against an empty database; no config or code changes required.
- **No breaking changes.** Upgrade with `composer update nowo-tech/migrations-kit-bundle` and clear cache.

---

## Upgrading to 1.0.0

This is the first release of `nowo-tech/migrations-kit-bundle`.

- **Package:** `nowo-tech/migrations-kit-bundle`
- **Config root:** `nowo_migrations_kit` with optional `connection` (default: `default`).
- **Bundle class:** `Nowo\MigrationsKitBundle\NowoMigrationsKitBundle`; register it in `config/bundles.php`.

If you were using the bundle from a dev or fork version, ensure you use the config key `nowo_migrations_kit` and the namespace `Nowo\MigrationsKitBundle`. See [CONFIGURATION.md](CONFIGURATION.md) and [INSTALLATION.md](INSTALLATION.md).

No upgrade steps are required when moving from a previous dev version that already used this namespace and config.
