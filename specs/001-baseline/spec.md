# Feature Specification: MigrationsKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/migrations-kit-bundle`  
**Configuration root**: `nowo_migrations_kit`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Doctrine Migrations helpers: **SchemaChecker** for idempotent existence checks and **CreateTablesService** for declarative MDK (Migration Definition Keys) schema arrays — SQLite, MySQL, PostgreSQL via DBAL 2–4. FrankenPHP worker friendly with kernel reuse (`FRANKENPHP_RESET_KERNEL` unset/false).

---

## User Scenarios & Testing

### User Story 1 — Idempotent schema checks (Priority: P1)

As a migration author, I use `SchemaChecker` before DDL so migrations skip work when tables, columns, indexes, or foreign keys already exist.

**Independent Test**: Run a migration twice against SQLite/MySQL/PostgreSQL; the second run performs no duplicate DDL when guards return true.

### User Story 2 — Declarative MDK migrations (Priority: P1)

As a migration author, I define schema changes as MDK arrays so `CreateTablesService` emits ordered SQL for create/alter/drop phases.

**Independent Test**: Feed a valid MDK array to `CreateTablesService::apply()` and assert SQL order respects FK/index dependencies.

### User Story 3 — Symfony demo compatibility (Priority: P2)

As a maintainer, I run the Symfony 8 FrankenPHP demo so DBAL 4 compatibility and worker mode stay verified.

**Independent Test**: `make demo-smoke` / `make release-check-demos` completes without migration or healthcheck errors.

### User Story 4 — FrankenPHP worker kernel reuse (Priority: P2)

As an integrator running FrankenPHP worker with `FRANKENPHP_RESET_KERNEL` unset or `0`, I use this bundle without per-request leaks from Migrations Kit state.

**Independent Test**: PHPStan includes classic + worker + worker-strict rulesets with zero findings on `src/`; [FRANKENPHP-WORKER-AUDIT.md](../../docs/FRANKENPHP-WORKER-AUDIT.md) verdict remains Compatible under scenario B.

---

## Requirements

- **FR-SCHEMA-001**: `SchemaChecker` exposes `tableExists`, `columnExists`, `indexExists`, `foreignKeyExists`, etc. without container injection.
- **FR-MIG-001**: `CreateTablesService::apply()` returns ordered SQL for create/alter/drop operations from MDK arrays.
- **FR-MIG-002**: `MigrationDefinitionKeys` documents canonical array keys (`tables`, `columns`, `indexes`, …).
- **FR-MIG-004**: Parser validates definition structure before SQL generation.
- **FR-MIG-005**: `IdField` provides reusable primary-key column template.
- **FR-WORKER-001**: Bundle helpers hold no per-request mutable state; safe when the FrankenPHP kernel is reused (`FRANKENPHP_RESET_KERNEL` unset/false) without relying on `services_resetter`.
- **FR-WORKER-002**: PHPStan FrankenPHP `ruleset-classic` and `ruleset-worker-strict` (includes worker) are included for `src/` analysis.
- **FR-WORKER-003**: Worker audit document is published and linked from README / DEMO-FRANKENPHP.

---

## Success Criteria

- **SC-001**: **12/12** files mapped.
- **SC-002**: Demo smoke / release-check-demos passes on Symfony 8.
- **SC-003**: FrankenPHP worker audit verdict Compatible (scenario B); PHPStan FrankenPHP rulesets clean.

---

## Validation

`composer qa`, demo `make migrate-dry-run` / `make demo-smoke`, PHPUnit, PHPStan with FrankenPHP rulesets.
