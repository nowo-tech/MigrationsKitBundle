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
