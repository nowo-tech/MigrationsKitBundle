# Feature Specification: Collation gate (`nowo:migrations:check-collations`)

**Feature Branch**: `feat/check-collations`  
**Created**: 2026-10-31  
**Status**: Implemented (Unreleased)  
**Input**: Host kit row KIT-50 (DB collation gate): an application shell script that fails CI when a table is not in the expected collation (a mixed collation broke a cross-module join with MySQL error 1267), with a short list of known tables allowed to differ. Generalised: expected collation from config or Doctrine `default_table_options`, columns and PostgreSQL support, ignore patterns, CI exit codes.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#collation-gate-nowomigrationscheck-collations), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#collation-check)

---

## User Scenarios & Testing

### User Story 1 — CI fails on a mixed collation (Priority: P1)

**Independent Test**: `tests/Collation/CollationCheckerTest`, `tests/Command/CheckCollationsCommandTest`.

1. **Given** a MySQL / MariaDB base table or string column whose collation differs (case-insensitive) from the expected one, **When** the command runs, **Then** each object is listed (`table <t>: collation <actual> (expected <x>)`, `column <t>.<c>: …`) and the exit code is 1.
2. **Given** every object matches, **Then** one `OK:` line with the tables / columns checked and the ignored objects still to convert, exit 0.
3. **Given** a table matching `ignore_tables` / `--ignore-table` (fnmatch) or a column matching `ignore_columns`, **Then** it may differ (listed with `-v`) but is not required to.
4. **Given** `check_database: true`, **Then** the database default collation and character set (PostgreSQL: `datcollate`, encoding) are compared too.
5. Views are not checked.

### User Story 2 — Platforms and configuration (Priority: P1)

1. PostgreSQL: only columns with an explicit `COLLATE` (and the database with `check_database`); tables have no collation.
2. SQLite and other platforms: `SKIP` line, exit 0 (dev / test databases do not fail the gate).
3. Expected collation: `--expected`, else `collation_check.expected_collation`, else the connection's `default_table_options.collation` (or `collate`); none on a supported platform → exit 2. A connection error → exit 2.
4. Connection: `--connection`, else `collation_check.connection`, else `nowo_migrations_kit.connection`.

**Integration**: `tests/Integration/MigrationsKitKernelTest::testCheckCollationsCommandIsRegisteredAndSkipsSqlite`. Verified manually against MySQL 8.4 and PostgreSQL 16 (mixed table / column collations, a view, the database default).
