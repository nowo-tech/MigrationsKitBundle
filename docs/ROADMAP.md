# Roadmap

This document outlines the direction of Migrations Kit Bundle and helps contributors and users understand upcoming priorities.

## Vision

Migrations Kit Bundle aims to provide **simple, reliable helpers for Doctrine Migrations** in Symfony: schema checks (table/column/index exist), array-based migration definitions, declarative schema (SchemaSync), and **data steps** (insert/update with optional checks) so you can write idempotent migrations without repeating SQL. The bundle stays compatible with multiple Doctrine DBAL and doctrine/migrations versions.

## Current focus (1.x)

- **Stability & compatibility:** The bundle is compatible with **Symfony 6, 7 and 8**, Doctrine DBAL 2.x/3.x/4.x, and doctrine/migrations 3.x/4.x. Fix regressions and deprecations as they appear.
- **Documentation:** Clear install, config, usage, upgrade, and example docs so new users can adopt the bundle quickly.
- **Testing:** Maintain test coverage and CI (PHP × Symfony matrix, code style).
- **Recipe:** Get the Flex recipe merged in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) so `composer require nowo-tech/migrations-kit-bundle` registers the bundle and config automatically.

No breaking changes are planned for the 1.x line; new options will be additive where possible.

## Short term (next releases)

- **Demos:** Keep demos (Symfony 6/7/8) working and referenced from the docs.
- **Code coverage:** Keep or raise coverage and ensure coverage runs in CI.
- **SchemaSync:** Refinements and documentation for edge cases (e.g. type changes, renames, MySQL limits).

## Implemented

- **Setting values (data):** MigrationDefinitionRunner supports a **`data`** key in the definition: **insert** (with optional `only_if_not_exists`) and **update** (with optional `only_if_exists`). SchemaChecker provides **rowExists($table, $conditions)** for checks. See [Declarative schema – Data steps](DECLARATIVE_SCHEMA.md#data-steps-insert--update) and demo migration Version20250219000005.
- **Standard columns (audit fields):** **StandardColumns** provides reusable definitions for `created_at`, `updated_at`, `created_by`, `updated_by` and their indexes: use **auditColumns()** / **auditIndexes()** in declarative schema, or **auditColumnSteps()** / **auditIndexSteps()** to add them to existing tables in migrations. See [Common column definitions](DECLARATIVE_SCHEMA.md#common-column-definitions-standardcolumns) and demo Version20250219000006.

## Possible future (ideas, not committed)
- **Additional DB platforms:** Improve or document behavior on PostgreSQL, SQLite, and other platforms where SQL differs.
- **Ecosystem:** Compatibility with future PHP and Symfony versions (e.g. PHP 8.4+, Symfony 9) when released.

A major version would only be considered if we introduce breaking changes (e.g. config structure, PHP/Symfony requirements, or DBAL/migrations API changes).

## Out of scope (for this bundle)

- **ORM schema diff / SchemaTool:** This bundle does not replace Doctrine ORM’s schema diff; it focuses on migrations and raw SQL / DBAL.
- **Migration generation from entities:** Migration *content* is written by you (or driven by the declarative array); the bundle does not generate migrations from entity mappings.
- **Multi-database migration orchestration:** Using multiple connections in one migration (e.g. with `withConnection`) is supported, but the bundle does not orchestrate ordering or cross-DB workflows.

## Community

- **Issues & PRs:** [GitHub Issues](https://github.com/nowo-tech/migrations-kit-bundle/issues) and Pull Requests are welcome.
- **Security:** Report vulnerabilities responsibly (e.g. via GitHub Security Advisories or the maintainers).

If you rely on Migrations Kit Bundle, consider giving it a **star** on GitHub so others can discover it more easily.
