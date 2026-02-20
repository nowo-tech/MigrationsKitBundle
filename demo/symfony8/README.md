# Migrations Kit Bundle – Demo (Symfony 8)

This demo uses **nowo-tech/migrations-kit-bundle** via a path repository (`../..`). All migrations in `migrations/` demonstrate the bundle:

- **Version20250219000000** – `MigrationDefinitionRunner::run()` with array (tables + columns)
- **Version20250219000001** – `SchemaChecker`: `tableExists()`, `columnExists()`
- **Version20250219000002** – `ensureTable()`, `ensureColumn()`, `ensureIndex()`
- **Version20250219000003** – `listTableColumns()` to add only missing columns
- **Version20250219000004** – `SchemaSync`: declarative schema from array (DBAL 3+)

## Run the demo

**With Docker (recommended):**

```bash
make up
```

Starts the container and runs migrations. Open http://localhost:8008 (or `$PORT`). The bundle is mounted at `/var/migrations-kit-bundle` in the container.

**Without Docker**, from this directory:

```bash
composer install
composer migrate
```

For local runs without Docker, set the path repo in `composer.json` to `../..` instead of `/var/migrations-kit-bundle`.

**From the bundle root:** `make demo-up-symfony8` then `make demo-migrate-symfony8`.

Database: SQLite at `var/data.db` (see `.env`). Compatible with MySQL and PostgreSQL; see `scripts/ensure-database.php`.
