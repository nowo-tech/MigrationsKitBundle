# Feature Specification: MigrationsKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/migrations-kit-bundle`  
**Configuration root**: `nowo_migrations_kit`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Doctrine Migrations helpers: **SchemaChecker** for idempotent existence checks and **CreateTablesService** for declarative MDK (Migration Definition Keys) schema arrays — SQLite, MySQL, PostgreSQL via DBAL 2–4.

---

## User Scenarios & Testing

### User Story 1 — Idempotent schema checks (Priority: P1)

As a migration author, I use `SchemaChecker` before DDL so migrations skip work when tables, columns, indexes, or foreign keys already exist.

**Independent Test**: Run a migration twice against SQLite/MySQL/PostgreSQL; the second run performs no duplicate DDL when guards return true.

### User Story 2 — Declarative MDK migrations (Priority: P1)

As a migration author, I define schema changes as MDK arrays so `CreateTablesService` emits ordered SQL for create/alter/drop phases.

**Independent Test**: Feed a valid MDK array to `CreateTablesService::apply()` and assert SQL order respects FK/index dependencies.

### User Story 3 — Symfony demo compatibility (Priority: P2)

As a maintainer, I run demos on Symfony 7 and 8 matrices so DBAL 3/4 compatibility stays verified in CI.

**Independent Test**: `make release-check-demos` completes on both demo stacks without migration errors.

---

## Requirements

- **FR-SCHEMA-001**: `SchemaChecker` exposes `tableExists`, `columnExists`, `indexExists`, `foreignKeyExists`, etc. without container injection.
- **FR-MIG-001**: `CreateTablesService::apply()` returns ordered SQL for create/alter/drop operations from MDK arrays.
- **FR-MIG-002**: `MigrationDefinitionKeys` documents canonical array keys (`tables`, `columns`, `indexes`, …).
- **FR-MIG-004**: Parser validates definition structure before SQL generation.
- **FR-MIG-005**: `IdField` provides reusable primary-key column template.

---

## Success Criteria

- **SC-001**: **12/12** files mapped.
- **SC-002**: Demo migrations run on Symfony 7 and 8 matrices.

---

## Validation

`composer qa`, demo `make migrate-dry-run`, PHPUnit.
