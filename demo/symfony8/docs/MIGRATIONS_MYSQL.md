# Demo: Symfony 8 — Migrations with MySQL 8

## Schema sync status: "Entities are not in sync with the database"

That screen compares the **current database schema** with the **entity mapping**. If you see CREATE TABLE for `kit_example`, `kit_user`, or changes to `kit_item`, it usually means one of two things:

### 1. Migrations have not been run yet (or not all of them)

**What to do:**

- With **MySQL 8** (`.env` with `DATABASE_URL="mysql://..."`):
  - From the host: `make migrate-mysql` (starts MySQL in Docker and runs migrations).
  - Or inside the container: `php bin/console doctrine:migrations:migrate --no-interaction`.
- With **SQLite** (default): `make migrate`.

Check which migrations have been applied:

```bash
php bin/console doctrine:migrations:status
```

If a migration fails (e.g. on MySQL), you will see the error when running `migrate`. Fix the error and run migrations again.

### 2. The `doctrine_migration_versions` table

The SQL list may show **"DROP TABLE doctrine_migration_versions"**. That is the table where Doctrine records executed migrations. **Do not run that DROP**: the application is configured to exclude it from the diff (`schema_filter` in `config/packages/doctrine.yaml`), so it should not appear. If you see it, ignore it.

### Demo migration order

1. **Version20250223100000** — Creates `kit_item` (id column only).
2. **Version20250223100001** — Creates `kit_example` (all column types).
3. **Version20250223100002** — Creates `kit_user` and adds `user_id` to `kit_item`.
4. **Version20250223100003** — Adds FK `kit_item.user_id` → `kit_user.id`.

If only 00000 was applied, the "Schema sync status" will show creating `kit_example`, `kit_user`, and modifying `kit_item`; that is normal until you run the remaining migrations.

### Summary

- Run **all** migrations: `make migrate` (SQLite) or `make migrate-mysql` (MySQL 8).
- Check `doctrine:migrations:status` and migrate error messages if something fails.
- Never run the DROP for `doctrine_migration_versions`.
