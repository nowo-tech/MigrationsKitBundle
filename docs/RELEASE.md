# Release process

## Release v2.0.4 (ready)

Documentation and changelog are prepared for **v2.0.4**. Before tagging, ensure the lock file is valid:

1. **Sync composer.lock** (required for `composer validate --strict` / `make release-check`):
   ```bash
   make composer-sync
   ```
   If you don't use Docker, run from the bundle root: `composer update --no-install` then `composer validate --strict`. Commit `composer.lock` if it changed.

2. **Run full release check** (optional but recommended):
   ```bash
   make release-check
   ```

3. **Commit, push, and tag**:
   ```bash
   git add -A
   git commit -m "Prepare v2.0.4 release"
   git push origin master
   git tag -a v2.0.4 -m "Release v2.0.4"
   git push origin v2.0.4
   ```

4. **(Optional)** Open GitHub → Releases → Draft a new release from tag `v2.0.4` and paste the [2.0.4] section from [CHANGELOG.md](CHANGELOG.md).

---

## Creating a new version (e.g. v2.0.0)

1. **Ensure everything is ready**
   - [CHANGELOG.md](CHANGELOG.md) has the target version (e.g. `[2.0.0]`) with date and full entry; `[Unreleased]` is at the top and empty or updated for the next cycle.
   - [UPGRADING.md](UPGRADING.md) has a section “Upgrading to X.Y.Z” with what’s new, breaking changes (if any), and upgrade steps.
   - **composer.lock** is up to date: run `make composer-sync` (or `composer update --no-install` then `composer validate --strict`) and commit the lock if changed.
   - Tests pass: `make test` or `composer test`.
   - Code style: `make cs-check` or `composer cs-check`.

2. **Commit and push** any last changes to your default branch (e.g. `main` or `master`):
   ```bash
   git add -A
   git commit -m "Prepare v2.0.1 release"
   git push origin HEAD
   ```

3. **Create and push the tag**
   ```bash
   git tag -a v2.0.1 -m "Release v2.0.1"
   git push origin v2.0.1
   ```

4. **GitHub Actions** (if configured) may create the GitHub Release from the tag.

5. **Packagist** (if the package is registered) will pick up the new tag; users can then `composer require nowo-tech/migrations-kit-bundle:^2.0`.

## After releasing

- Keep `## [Unreleased]` at the top of [CHANGELOG.md](CHANGELOG.md) for the next version; add new changes there.
- Optionally bump a dev version in `composer.json` for development.

---

## v2.0.4 (2025-02-27)

- **Scope:** FK options (onDelete/onUpdate) fixed in generated SQL (DBAL 3 vs 4 parameter order); SchemaAssetName reflection to avoid getName() deprecation; drop-column FK normalization to avoid "columns referenced by constraints" deprecation; tests and demo validation for FK options; DECLARATIVE_SCHEMA updated.
- **Checklist:** CHANGELOG and UPGRADING updated. Run `make composer-sync`, then `make release-check`, commit, push, and tag v2.0.4.

---

## v2.0.3 (2025-02-27)

- **Scope:** Documentation for Doctrine deprecations (transactional/DDL on MySQL, AbstractAsset::getName() in DBAL 5). SchemaAssetName and tests prepared for DBAL 5. No API or config changes.
- **Checklist:** CHANGELOG and UPGRADING updated. Run `make composer-sync`, then `make release-check`, commit, push, and tag v2.0.3.

---

## v2.0.2 (2025-02-25)

- **Scope:** CreateTablesService: add column + index + FK on new columns in one apply(); DEMO_MIGRATIONS_REFERENCE updated; unit test for the new behaviour.
- **Checklist:** CHANGELOG and UPGRADING updated. Run `make composer-sync`, then `make release-check`, commit, push, and tag v2.0.2.

---

## v2.0.1 (2025-02-25)

- **Scope:** Documentation and release process. RELEASE.md updated with composer.lock sync step and checklist.
- **Checklist:** CHANGELOG and UPGRADING updated. Run `make composer-sync`, then commit, push, and tag v2.0.1.

---

## v2.0.0 (2025-02-25)

- **Scope:** Major release. Removed MigrationDefinitionRunner, SchemaSync, StandardColumns, MigrationDefinition, data steps, rowExists; API is now SchemaChecker + CreateTablesService (MDK) only. DBAL 2.x compatibility; getDropPrimaryKeySQL reflection fallback for protected method; quoted identifiers in SQL fallbacks; demo migrations 00011–00013 (DROP_PRIMARY_KEYS, PRIMARY_KEY); docs and Makefiles aligned; MIGRATIONS_VALIDATION.md and USAGE export-SQL section.
- **Checklist:** CHANGELOG and UPGRADING updated. Tag v2.0.0.

---

## v1.2.1 (2026-02-22)

- **Scope:** DBAL 4 compatibility (SchemaSync, SchemaDefinitionParser), MigrationDefinitionRunner MDK fix, docs/comments in English, test coverage improvements.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v1.2.1` created and pushed.

---

## v1.2.0 (2026-02-20)

- **Scope:** MigrationDefinitionRunner steps (indexes, rename_columns, modify_columns, drop_indexes, drop_columns), modifyColumn/dropColumn/dropIndex/ensureForeignKey, SchemaCheckerInterface, MigrationDefinition typed, PHPUnit/composer/Makefile/coverage fixes, docs and Makefile in English.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v1.2.0` created and pushed.

---

## v1.1.0 (2026-02-20)

- **Scope:** Data steps in MigrationDefinitionRunner, SchemaChecker rowExists/getConnection, StandardColumns, SchemaSync PostgreSQL fix, demo SQL viewing targets, demo README in English.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v1.1.0` created and pushed.

---

## v1.0.0 (2026-02-20)

- **Scope:** First release. SchemaChecker, MigrationDefinitionRunner, SchemaSync, configuration, demos (Symfony 7/8), docs, Makefile, Docker, CI.
- **Checklist:** CHANGELOG and UPGRADING updated; tag `v1.0.0` created and pushed.
