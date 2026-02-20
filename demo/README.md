# Demos - Migrations Kit Bundle

Each demo is a minimal **Dockerized** Symfony app with **FrankenPHP** and a **Caddyfile** serving the app over HTTP (port 80 inside the container).

| Demo     | PHP   | Port  | URL (with Docker)      |
|----------|-------|-------|------------------------|
| symfony6 | 8.2   | 8006  | http://localhost:8006  |
| symfony7 | 8.2   | 8007  | http://localhost:8007  |
| symfony8 | 8.4   | 8008  | http://localhost:8008  |

Each demo includes **Web Profiler**, **Debug** and **entities** aligned with the migrations (DemoKitUser, DemoKitAppSetting, DemoKitAuditLog, DemoKitProduct).

## Requirements

- Docker and Docker Compose
- Bundle repo cloned (demos mount the bundle from `../..` at `/var/migrations-kit-bundle`; the path repo in each demo points to the bundle inside the container)

## Quick start

From each demo directory (e.g. `demo/symfony8`):

```bash
make up        # Start the container and serve the app
# Open http://localhost:8008 (symfony8) and you'll see "Schema sync status": entities not in sync
make migrate   # Create var/ and run doctrine:migrations:migrate
# Refresh the page: entities are now in sync
# Or do it all at once:
make setup     # install + migrate (run before opening the URL if you want "in sync" from the start)
```

**Schema status route:** The root (`/`) of each demo shows whether **Doctrine entities** are in sync with the **database**. Before running migrations you'll see schema errors; after `make migrate`, the page will show "Entities are in sync with the database".

From the bundle root (without Docker):

```bash
make demo-up-symfony8      # install in demo/symfony8
make demo-migrate-symfony8 # run migrations in demo/symfony8
```

## Make targets (inside each demo)

- **up** – Start the container (runs in background).
- **down** – Stop the container.
- **build** – Rebuild the image with no cache.
- **install** – `composer install` in the container (bundle from `/var/migrations-kit-bundle` via path repo).
- **migrate** – Create `var/` if needed and run `composer migrate` (doctrine:database:create + doctrine:migrations:migrate).
- **migrate-verbose** – Run migrations and show SQL on screen (`-vv`).
- **migrate-dry-run** – Show SQL that would run without applying changes.
- **migrate-write-sql** – Write pending migration SQL to `var/migration.sql`.
- **setup** – install + migrate.
- **shell** – Open a shell in the PHP container.
- **logs** – Show container logs.
- **cache-clear** – Clear Symfony cache.
- **update-bundle** – Update the bundle from the mounted path and clear cache.

## Viewing migration SQL

To validate or inspect the SQL executed by each migration:

- **make migrate-verbose** – Run migrations and display each migration’s SQL (uses Doctrine `-vv`).
- **make migrate-dry-run** – Show the SQL that *would* run without applying changes (useful for pending migrations).
- **make migrate-write-sql** – Write pending SQL to `var/migration.sql` for review.

From inside the container you can also run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction -vv   # run and show SQL
php bin/console doctrine:migrations:migrate --dry-run -vvv          # only show SQL (no execution)
php bin/console doctrine:migrations:migrate latest --write-sql=var/migration.sql --no-interaction
```

## Demo structure

- **Dockerfile** – **FrankenPHP** image (Alpine), extensions `zip`, `intl`, `pdo_sqlite`, Composer, Caddyfile and entrypoint.
- **docker/frankenphp/Caddyfile** – Caddy: listen on `:80`, document root in `public`, compression (zstd, br, gzip), PHP worker for `index.php`.
- **docker/entrypoint.sh** – Creates `var/`, runs `composer install` if there is no `vendor`, starts FrankenPHP with the Caddyfile.
- **docker-compose.yml** – `php` service, bundle and demo mount, container port `80` mapped to `8006`/`8007`/`8008` on the host.
- **Makefile** – Targets listed above.

## Tables created by the migrations

- **demo_kit_users** – id, name, email (V00000), phone, notes (V00003), created_at, updated_at, created_by, updated_by + indexes (V00006, StandardColumns)
- **demo_kit_app_settings** – id, key_name, value, created_at
- **demo_kit_audit_log** – id, action, created_at + index on `action`
- **demo_kit_product** – id, name, price, created_at + index on name (V00004, SchemaSync)

Demos use SQLite by default (`var/data.db`). You can switch to MySQL in `.env`; migrations adapt SQL to the driver.

## Example migrations

| Version              | Description | What it demonstrates |
|----------------------|-------------|------------------------|
| **Version20250219000000** | Create `demo_kit_users` table and `email` column | **Array-based**: `MigrationDefinitionRunner::run()` with `tables` and `columns`. |
| **Version20250219000001** | Create `demo_kit_app_settings` table and `created_at` column | **SchemaChecker**: `tableExists()`, `columnExists()`. |
| **Version20250219000002** | Create `demo_kit_audit_log` with column and index | **Direct methods**: `ensureTable()`, `ensureColumn()`, `ensureIndex()`. |
| **Version20250219000003** | Add `phone` and `notes` columns to `demo_kit_users` | **listTableColumns()**: add only missing columns. |
| **Version20250219000004** | Create/update `demo_kit_product` from array | **SchemaSync**: declarative schema in an array (DBAL 3+). |
| **Version20250219000005** | Insert/update data in `demo_kit_app_settings` | **Data steps**: `run()` with `data` (insert/update, only_if_not_exists, only_if_exists). |
| **Version20250219000006** | Add audit columns to `demo_kit_users` | **StandardColumns**: `auditColumnSteps()`, `auditIndexSteps()` for standard fields. |
