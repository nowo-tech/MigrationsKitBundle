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
- Demos for Symfony 6, 7 and 8 with doctrine/migrations 3.x and 4.x.
- Documentation in `docs/` (CONFIGURATION, INSTALLATION, USAGE, DECLARATIVE_SCHEMA, CONTRIBUTING, EXAMPLE, RELEASE, ROADMAP, UPGRADING).
- Makefile, Docker, and GitHub CI for development and releases.
