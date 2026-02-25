# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- (No changes yet.)

### Changed

- (No changes yet.)

### Fixed

- (No changes yet.)

---

## [2.0.0] - 2025-02-25

**Major release: incompatible with 1.x.** The bundle now exposes only **SchemaChecker** and **CreateTablesService** (MDK declarative definitions). All previous runners, sync, and data-step APIs have been removed.

### Breaking changes

- **Removed `MigrationDefinitionRunner`** — No longer available. Use **CreateTablesService::apply()** with a definition array (MDK format) instead. Replace `ensureTable` / `ensureColumn` / `ensureIndex` with table definitions under `MDK::TABLES`; run returned SQL in a loop with `$this->addSql($sql)` or pass `$this` as the emitter.
- **Removed `SchemaSync`** — Declarative “sync from array” is replaced by **CreateTablesService::apply()**: pass the migration’s `Schema` and your definition; the service returns the list of SQL statements to run.
- **Removed `StandardColumns`** — No replacement. Define audit columns (e.g. `created_at`, `updated_at`) directly in your MDK definition arrays.
- **Removed `MigrationDefinition`** (typed value object) — Use plain arrays with **MigrationDefinitionKeys (MDK)** constants and pass them to **CreateTablesService::apply()**.
- **Removed data steps** — The `data` key and insert/update steps (`only_if_not_exists`, `only_if_exists`) are no longer supported. Perform data migrations with raw `$this->addSql()` or your own logic.
- **Removed `SchemaChecker::rowExists()`** — Use the migration connection and run a simple `SELECT` (or inject a repository) when you need to check row existence.
- **Removed direct runner methods** — `modifyColumn()`, `dropColumn()`, `dropIndex()`, `ensureForeignKey()` no longer exist. Use **CreateTablesService** with MDK `columns` / `indexes` / `foreign_keys` and `drop` / `drop_columns` / `drop_indexes` / `drop_foreign_keys` keys.

### Added

- **CreateTablesService::applyWithAddSql()** — Apply a definition with a custom callable to emit SQL (e.g. for logging or custom handling).
- **SchemaChecker::getSchemaManager()** — Access the DBAL schema manager (DBAL 2.x and 3.x/4.x compatible).
- **DBAL 2.x compatibility in CreateTablesService** — `resolveTableName()` supports both `Schema::getTables()` (DBAL 3+) and `Schema::getTableNames()` (DBAL 2.x) for correct table name resolution.
- **PHPUnit coverage configuration** — `<coverage>` in `phpunit.xml.dist` with HTML report and bounds (90% / 95%) for development.
- Additional tests for SchemaChecker (exception paths), CreateTablesService (warn-on-mix, rename+index, drop PK, column options), and SchemaDefinitionParser (foreign_keys alias).
- Demo migrations Version20250223100011–00013 (create table kit_pk_demo, DROP_PRIMARY_KEYS, PRIMARY_KEY on existing table); USAGE.md section "Exporting and viewing SQL before applying"; demo MIGRATIONS_VALIDATION.md (symfony7/symfony8).

### Changed

- **Documentation** — README, USAGE, CONFIGURATION, UPGRADING, INSTALLATION, and DECLARATIVE_SCHEMA describe only the 2.0 API. Option `connection` documented for CreateTablesService (when injected from container). DEMO_MIGRATIONS_REFERENCE and demo README/Makefile in English. References to removed APIs archived in CHANGELOG.
- **Demos** — Demo migrations use only CreateTablesService and MDK; Makefiles aligned (symfony7/symfony8).

### Fixed

- **CreateTablesService (DBAL 2.x)** — Fixed “Call to undefined method Schema::getTableNames()” when resolving table names; uses `getTables()` when available and falls back to `getTableNames()` on DBAL 2.x.
- **CreateTablesService (MySQL / protected getDropPrimaryKeySQL)** — When the platform's `getDropPrimaryKeySQL()` is protected (e.g. some DBAL versions), use reflection to call it only if public; otherwise generate `ALTER TABLE … DROP PRIMARY KEY` with quoted table name.
- **CreateTablesService** — Fallbacks that build SQL manually (DROP TABLE, DROP FOREIGN KEY) now use quoted table and identifier names via `quotedTableName()` and `quoteSingleIdentifier()` for reserved/special characters.

---

## [1.2.1] - 2026-02-22

### Fixed

- **SchemaSync (DBAL 4):** Column type name resolution when building tables: use `Type::lookupName()` when `Type::getName()` is not available (DBAL 4). Ensures `buildTableWithShortNameOnly` works with DBAL 4.
- **SchemaSync (DBAL 4):** Support `SchemaDiff::getAlteredTables()` in addition to `getModifiedTables()` for modified tables; support `TableDiff::getOldTable()->getName()` for diff table name when `name` / `getName()` are not present.
- **SchemaSync (DBAL 4):** Use `getDropTablesSQL()` when available for dropping tables; fallback to per-table `getDropTableSQL()` with quoted names for older platforms.
- **SchemaDefinitionParser (DBAL 4):** Broader exception handling when `Schema::createTable(Table)` fails (e.g. "must be of type string"), so the reflection-based table injection works with current DBAL 4.
- **MigrationDefinitionRunner:** Add missing `use` for `MigrationDefinitionKeys as MDK` so `run()` works when using the constants class.

### Changed

- **Documentation:** All user-facing docs and comments in English (README, USAGE.md, DECLARATIVE_SCHEMA.md, phpunit.xml.dist).
- **Tests:** Improved test coverage (MigrationDefinitionRunner, SchemaChecker, SchemaLimitChecker, SchemaDefinitionParser, MigrationDefinition, StandardColumns, DependencyInjection, SchemaSync tests with mocks). Coverage report excludes `SchemaSync.php` from the percentage (complex DBAL integration); remaining code meets the coverage target.

---

## [1.2.0] - 2026-02-20

### Added

- **MigrationDefinitionRunner:** New step keys in `run()`: `indexes` (add index if not exists), `rename_columns` (run rename_sql if old column exists), `modify_columns` (run modify_sql if column exists), `drop_indexes` (run drop_sql if index exists), `drop_columns` (run drop_sql if column exists). Order: tables → columns → indexes → rename_columns → modify_columns → drop_indexes → drop_columns → data. See [USAGE.md](USAGE.md).
- **Direct methods:** `modifyColumn()`, `dropColumn()`, `dropIndex()`, `ensureForeignKey()` — run the given SQL only when the condition holds (column/index/FK exists or not).
- **SchemaCheckerInterface:** New interface for schema checks; `SchemaChecker` implements it. Enables mocking in unit tests (PHPUnit cannot mock final classes). Service alias in DI: `SchemaCheckerInterface` → `SchemaChecker`.
- **MigrationDefinition:** Typed value object for the full definition (tables, columns, indexes, renameColumns, modifyColumns, dropIndexes, dropColumns, data). Use `new MigrationDefinition(...)->run($runner, $addSql)` or `MigrationDefinition::fromArray([...])->run($runner, $addSql)`. PHPStan types documented. See [USAGE.md](USAGE.md#migrationdefinition-typed).
- **Composer/Makefile:** Scripts use `vendor/bin/phpunit` and `vendor/bin/php-cs-fixer`. `make test`, `make test-coverage`, `make cs-check`, `make qa` depend on `install` so the container has dependencies before running.
- **Coverage in console:** `composer test-coverage` uses `--coverage-text=php://stdout` and `--colors=always` so coverage percentage is shown in the terminal.
- **Makefile and Dockerfile:** Comments and help text in English; Dockerfile documents PCOV for coverage in the container.

### Changed

- **phpunit.xml.dist:** Removed invalid `<coverage>` block (PHPUnit 10.5 XSD); coverage is driven by CLI options in `test-coverage` script.

### Fixed

- **Tests:** MigrationDefinitionRunner tests now mock `SchemaCheckerInterface` instead of final `SchemaChecker`, fixing "Class is declared final and cannot be doubled" (21 tests).

---

## [1.1.0] - 2026-02-20

### Added

- **Data steps in MigrationDefinitionRunner:** `run()` accepts a `data` key with insert/update steps; supports `only_if_not_exists` (insert only when no row matches) and `only_if_exists` (update only when a row exists). See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#data-steps).
- **SchemaChecker:** `rowExists($table, array $criteria)` to check if a row exists by column values; `getConnection()` for direct DB access.
- **StandardColumns:** New `Nowo\MigrationsKitBundle\Schema\StandardColumns` class for reusable audit fields: `auditColumns()`, `auditIndexes()`, `auditColumnSteps()`, `auditIndexSteps()`, `timestampColumnSteps()`, `userRefColumnSteps()`. Use in declarative schema (SchemaSync) or with MigrationDefinitionRunner. See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#common-column-definitions-standardcolumns).
- **Demo:** Example migrations for data steps (Version20250219000005) and StandardColumns (Version20250219000006). Make targets `migrate-verbose`, `migrate-dry-run`, `migrate-write-sql` to view migration SQL. Demo README translated to English.

### Fixed

- **SchemaSync (PostgreSQL):** Resolved "There is no table with name public.xxx" when syncing against an empty or new database. New tables are created from the definition only; when current schema is empty, the modified-tables loop is skipped; compareSchemas is wrapped in try-catch with fallback to build new tables from definition.

---

## [1.0.0] - 2026-02-20

First release under **nowo-tech**.

### Added

- **SchemaChecker:** `tableExists`, `columnExists`, `indexExists`, `hasPrimaryKey`, `foreignKeyExists`, `listTableColumns`; compatible with DBAL 2.x, 3.x and 4.x.
- **MigrationDefinitionRunner:** run from array (`tables` + `columns`), `ensureTable`, `ensureColumn`, `ensureIndex`; only runs SQL when the target does not exist.
- **SchemaSync (declarative schema):** desired schema in one array; create/alter/drop tables, columns, indexes; requires DBAL 3.x or 4.x.
- **Configuration:** `nowo_migrations_kit.connection` for the injected SchemaChecker service.
- Demos for Symfony 7 and 8 with doctrine/migrations 3.x and 4.x.
- Documentation in `docs/` (CONFIGURATION, INSTALLATION, USAGE, DECLARATIVE_SCHEMA, CONTRIBUTING, EXAMPLE, RELEASE, ROADMAP, UPGRADING).
- Makefile, Docker, and GitHub CI for development and releases.
