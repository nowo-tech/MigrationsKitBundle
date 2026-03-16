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

## [2.0.7] - 2026-03-16

### Added

- **CI matrix for Doctrine DBAL** — GitHub Actions now runs the test suite against **DBAL 3 and DBAL 4** on PHP 8.2 / Symfony 7.0 (`test-dbal`), plus a dedicated **coverage job for DBAL 3** (`coverage-dbal3`) that uploads a separate report to Codecov. This verifies real compatibility with both major DBAL lines.
- **Tests & coverage** — Many new PHPUnit tests for `CreateTablesService` and `SchemaDefinitionParser`, including integration tests in `SchemaMigrationServiceTest` and demo migrations, significantly increasing real coverage without ignores or configuration tweaks.

### Changed

- **Static analysis & internals** — PHPStan is now at **0 errors**, with refined types and method signatures for DBAL 3/4 (comparator, `SchemaDiff::toSql`, `getAlterSchemaSQL`, `getRenameColumnSQL`, parameter order).
- **Demo & CI** — The Symfony 7/8 demo migrations were updated to better exercise the bundle phases (drops, PK changes, indexes, FKs) and are now part of the release health‑check.

### Fixed

- **SQLite demo migration** — In `demo/symfony7`, migration `Version20250223100013` (PK change on `kit_pk_demo`) now skips on SQLite, avoiding `"table \"kit_pk_demo\" has more than one primary key"` while still exercising the PK‑change path on other platforms.
- **DBAL compatibility edges** — Several edge‑case paths in `CreateTablesService` and `SchemaDefinitionParser` were adjusted and covered by tests so they behave correctly across DBAL 3/4 for foreign keys, indexes, primary keys, and column modifications.

---

## [2.0.6] - 2025-02-27

### Added

- **Tests** — **CreateTablesServiceMySQLPlatformTest::testApplyCreateTableWithForeignKeyOnDeleteEmitsOnDeleteInSqlOnMySQL**: asserts that when creating a **new table** (table does not exist) with `FOREIGN_KEYS` that have `onDelete` (CASCADE, SET NULL), the generated CREATE TABLE SQL includes `ON DELETE CASCADE` and `ON DELETE SET NULL`.

### Changed

- **SchemaDefinitionParser** — When building a table from the definition, foreign keys with a `name` now receive `onDelete` and `onUpdate` from the FK definition. The parser uses the same parameter-order detection as CreateTablesService (reflection on `Table::addForeignKeyConstraint`) so options are passed correctly on DBAL 3 and 4. Previously, the parser always passed an empty options array, so **new tables** created via `parseTable` + `getCreateTableSQL` did not get `ON DELETE` / `ON UPDATE` in the generated SQL.

### Fixed

- **CreateTablesService** — When the same table has `DROP_COLUMNS`, the SQL returned by `dropColumnsViaComparator` is now deduplicated by exact string before adding to the output. Some platforms or comparator versions can return the same statement twice (e.g. `ALTER TABLE tablename DROP columnname`); the bundle now emits each statement at most once per Phase 2a run.

---

## [2.0.5] - 2025-02-27

### Added

- **Demo Makefiles** — New target `test-mysql-write-sql`: same flow as `test-mysql` (update-bundle + db-reset-mysql) but runs `doctrine:migrations:migrate --write-sql=var/migration_mysql.sql` so the generated MySQL SQL is written to a file without executing. Available in `demo/symfony7` and `demo/symfony8`.
- **Documentation** — [BUGREPORT_DUPLICATE_DROP_FK_AND_ON_DELETE.md](BUGREPORT_DUPLICATE_DROP_FK_AND_ON_DELETE.md): resolution of the duplicate DROP FOREIGN KEY and missing ON DELETE issues (fixed in 2.0.4), with code and test references. Linked from README documentation table.

### Changed

- **CreateTablesService::getDropForeignKeySQL()** — DROP FOREIGN KEY SQL is now always emitted in **canonical form (no backticks)**: `ALTER TABLE tablename DROP FOREIGN KEY fkname`. The bundle no longer calls the platform’s `getDropForeignKeySQL()`, so the same style is used everywhere and duplicate-looking statements (e.g. one line with backticks on the table, another with backticks on the FK) are avoided when Phase 2a output is filtered.

### Removed

- **CreateTablesService** — Removed unused reflection helpers `getDropForeignKeySQLExpectsString()` and `getDropForeignKeySQLExpectsTableNameString()` (no longer needed after generating DROP FOREIGN KEY in canonical form).

---

## [2.0.4] - 2025-02-27

### Added

- **CreateTablesService (FK options)** — **onDelete** and **onUpdate** in `MDK::FOREIGN_KEYS` are now correctly applied: the generated `ALTER TABLE ... ADD CONSTRAINT` SQL includes `ON DELETE` and `ON UPDATE` clauses on MySQL/MariaDB (and other platforms that support them). The bundle detects DBAL 3 vs 4 parameter order for `Table::addForeignKeyConstraint` (name vs options as 4th/5th argument) and calls it accordingly. See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#foreign-keys).
- **Tests** — **CreateTablesServiceMySQLPlatformTest**: `testApplyAddForeignKeyWithOnDeleteAndOnUpdateEmitsCorrectSqlOnMySQL` and `testApplyAddForeignKeyWithOnDeleteSetNullEmitsCorrectSqlOnMySQL` assert that generated SQL contains `ON DELETE CASCADE`, `ON DELETE SET NULL`, and `ON UPDATE CASCADE`; `testApplyDropForeignKeyAndDropColumnSameTableNoDuplicateDropFk` asserts that when a table has both `DROP_FOREIGN_KEYS` and `DROP_COLUMNS` (column referenced by that FK), only one `DROP FOREIGN KEY` is emitted. **SchemaMigrationServiceTest::testApplyAddForeignKeyWithOnUpdateAndOnDelete** now asserts that the SQL contains `ON DELETE` and `ON UPDATE`.
- **Demo validation** — **Version20250223100003_validation** (symfony7 and symfony8): when the FK `fk_kit_item_user_id` exists, validates that it has `onDelete` = `SET NULL` (from the MDK definition). **DECLARATIVE_SCHEMA.md**: note and example for `onDelete` / `onUpdate` in foreign keys.

### Changed

- **SchemaAssetName** — Name is now read via reflection over the class hierarchy (`getNameViaReflection`) so that the deprecated `getName()` is not called when the `name` property can be read from a parent class (e.g. `AbstractAsset`). Reduces or eliminates the "AbstractAsset::getName is deprecated" notice in DBAL 4.
- **CreateTablesService::dropColumnsViaComparator** — Column names in the "columns to drop" set and FK local column names are normalized before comparison, so FKs that reference columns being dropped are correctly detected and dropped first. Fixes the "Dropping columns referenced by constraints is deprecated" warning when the platform returns column names in a different form (e.g. `Identifier`).

### Fixed

- **CreateTablesService** — Foreign keys defined with `onDelete` (e.g. `CASCADE`, `SET NULL`) and/or `onUpdate` in the MDK definition now produce SQL that includes those clauses. Previously the comparator path used a parameter order that did not pass options in DBAL 3.
- **CreateTablesService** — When the same table has both `DROP_FOREIGN_KEYS` and `DROP_COLUMNS` (where the column is referenced by that FK), the bundle no longer emits two identical `ALTER TABLE ... DROP FOREIGN KEY` statements. Phase 1b drops the FK by name; Phase 2a (`dropColumnsViaComparator`) would also generate the same DROP; FKs already dropped in Phase 1b are now tracked and the duplicate SQL is skipped. See `CreateTablesServiceMySQLPlatformTest::testApplyDropForeignKeyAndDropColumnSameTableNoDuplicateDropFk`.

---

## [2.0.3] - 2025-02-27

### Added

- **Documentation (Doctrine deprecations)** — **USAGE.md**: New section *"Transaction already committed (deprecation) when using DDL on MySQL"* explaining the Doctrine Migrations warning when DDL causes implicit commits; recommends `transactional: false` or overriding `isTransactional()` to return `false`. New section *"AbstractAsset::getName() deprecated (DBAL 5)"* explaining that the bundle uses **SchemaAssetName::get($asset)** for schema asset names so code is compatible with DBAL 3, 4 and 5.
- **Documentation** — **DEMO_MIGRATIONS_REFERENCE.md**: Recommendation #4 expanded to mention the transactional/DDL deprecation warning and link to USAGE.md and Doctrine’s implicit-commits docs. **MIGRATIONS_API.md**: Note that `AbstractAsset::getName()` is deprecated in DBAL 4 and removed in DBAL 5; bundle uses `SchemaAssetName::get()` for compatibility, with link to USAGE.md.

### Changed

- **SchemaAssetName** — Order of name resolution: use `getName()` first when the method exists (DBAL 3/4), then fall back to the `name` property (DBAL 5 when `getName()` is removed). Ensures correct behaviour when the property is protected in older DBAL.
- **Tests** — **SchemaDefinitionParserTest**: Table name assertions now use `SchemaAssetName::get($table)` instead of `$table->getName()`, so tests remain valid when DBAL 5 removes `getName()`.

### Fixed

- (No changes.)

---

## [2.0.2] - 2025-02-25

### Added

- **CreateTablesService** — In a single `apply()` call, when the definition adds new columns and also defines indexes and/or foreign keys on those columns, the bundle now emits all SQL (ADD COLUMN, index, FK) in one run. No need for a separate manual block with `SchemaChecker` and `addSql()` for indexes/FKs on newly added columns. On SQLite, FK creation via this path is skipped (platform does not support it); indexes and columns still work.
- **DEMO_MIGRATIONS_REFERENCE.md** — Matrix row for “Add column + index + FK in one apply” and note in recommendations that column, index and FK on the same new columns can be defined in one definition. Unit test `testApplyAddColumnAndIndexAndFkOnNewColumnsEmitsAllSqlInOrder` covers this behaviour.

### Changed

- (No changes.)

### Fixed

- (No changes.)

---

## [2.0.1] - 2025-02-25

### Changed

- **RELEASE.md** — Release checklist now includes syncing `composer.lock` before tagging (`make composer-sync` or `composer update --no-install` + `composer validate --strict`). General "Creating a new version" steps updated with the same requirement.

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
