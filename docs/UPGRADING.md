# Upgrade Guide

This guide explains how to upgrade Migrations Kit Bundle between versions. For a list of changes in each version, see [CHANGELOG.md](CHANGELOG.md).

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
