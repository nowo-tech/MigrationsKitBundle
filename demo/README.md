# Demos - Migrations Kit Bundle

Each demo is a minimal **Dockerized** Symfony app with **FrankenPHP** and a **Caddyfile** serving the app over HTTP (port 80 inside the container).

| Demo     | PHP   | Port  | URL (with Docker)      |
|----------|-------|-------|------------------------|
| symfony8 | 8.4   | 8008  | http://localhost:8008  |

Each demo includes **Web Profiler**, **Debug** and one entity **KitItem** aligned with the migrations. Migrations use **CreateTablesService** (phase 3: create tables and add missing columns).

## Requirements

- Docker and Docker Compose
- Bundle repo cloned (demos mount the bundle from `../..` at `/var/migrations-kit-bundle`; the path repo in each demo points to the bundle inside the container)

## Quick start

From each demo directory (e.g. `demo/symfony8`):

```bash
make up        # Start container + app. Does NOT run migrations.
# Open http://localhost:8008 (symfony8) — "Schema sync status": entities not in sync until you run migrations
make migrate   # Create var/ and run doctrine:migrations:migrate
# Refresh the page: entities are now in sync
# Or:
make setup     # install + migrate (run migrations)
```

**Note:** `make up` only starts the container and installs dependencies; it does **not** run migrations. Run `make migrate` (or `make setup`) to apply migrations.

**Schema status route:** The root (`/`) shows whether **Doctrine entities** are in sync with the **database**. Run `make migrate` (or `make setup`) first; then the page shows "Entities are in sync with the database".

From the bundle root (without Docker):

```bash
make demo-up-symfony8      # install in demo/symfony8
make demo-migrate-symfony8 # run migrations in demo/symfony8
```

## Make targets (inside each demo)

- **up** – Start the container (runs in background). **Does not run migrations.**
- **down** – Stop the container.
- **build** – Rebuild the image with no cache.
- **install** – `composer install` in the container (bundle from `/var/migrations-kit-bundle` via path repo).
- **migrate** – Create `var/` if needed and run migrations (SQLite by default).
- **migrate-verbose** – Run migrations and show SQL on screen (`-vv`).
- **migrate-dry-run** – Show SQL that would run without applying changes.
- **migrate-write-sql** – Write pending migration SQL to `var/migration.sql`.
- **write-sql-reference-mysql** – (symfony8) Reset MySQL and write all migration SQL to `var/expected_migrations_mysql.sql` for validation. See [docs/DEMO_MIGRATIONS_REFERENCE.md](../docs/DEMO_MIGRATIONS_REFERENCE.md).
- **up-mysql** – Start PHP + MySQL 8 container (for MySQL tests).
- **db-reset-mysql** – Drop and recreate the MySQL database (fresh for `migrate-mysql`).
- **migrate-mysql** – Run migrations against MySQL 8.
- **setup** – install + migrate.
- **shell** – Open a shell in the PHP container.
- **logs** – Show container logs.
- **cache-clear** – Clear Symfony cache.
- **update-bundle** – Update the bundle from the mounted path and clear cache.

## Viewing migration SQL

**Recommended:** check the SQL before applying (e.g. `make migrate-dry-run` or `doctrine:migrations:migrate --dry-run -vvv`). See [docs/USAGE.md](../docs/USAGE.md#viewing-sql-before-running-migrations).

- **make migrate-verbose** – Run migrations and display each migration’s SQL.
- **make migrate-dry-run** – Show the SQL that *would* run without applying changes.
- **make migrate-write-sql** – Write pending SQL to `var/migration.sql`.

## Demo structure

- **Dockerfile** – **FrankenPHP** image (Alpine), extensions `zip`, `intl`, `pdo_sqlite`, `pdo_mysql`, Composer, Caddyfile and entrypoint.
- **docker/frankenphp/Caddyfile** – Caddy: listen on `:80`, document root in `public`, compression, PHP worker for `index.php`.
- **docker-compose.yml** – `php` service (SQLite by default), optional `mysql` service (MySQL 8) for tests; container port `80` mapped to `8008` on the host.
- **Makefile** – Targets listed above.

## Tables and migrations

- **kit_item** – Table with one column: `id` (integer, primary key, autoincrement). Created by the bundle’s **CreateTablesService** (MDK definition).
- **Version20250223100000** – Creates table `kit_item` with one simple column (id) and primary key (introspectSchema + apply).
- **kit_example** – Demo table with all supported column types and options: id, smallint, bigint, boolean, decimal (precision/scale), float, string (length), string nullable, text, ascii_string, datetime, datetime_immutable, date, time, json, blob, guid, and a column with comment.
- **Version20250223100001** – Creates table `kit_example` with the full set of column types and options (notnull, default, length, precision, scale, comment).
- **Version20250223100002–00010** – Add/drop columns and FKs on kit_item/kit_user; drop table; rename column (00008), modify column (00009), add indexes/unique (00010). See `migrations/` in each demo and [DEMO_MIGRATIONS_REFERENCE.md](../docs/DEMO_MIGRATIONS_REFERENCE.md).

Demos use **SQLite** by default (`var/data.db`). To run the same migrations against **MySQL 8** (e.g. for cross-DB tests):

```bash
make up-mysql          # start PHP + MySQL 8
make db-reset-mysql     # drop and recreate database
make migrate-mysql      # run migrations against MySQL 8
```

MySQL 8 runs in a separate container; connection is `mysql://app:app@mysql:3306/demo`. Port on host: symfony8 `3308` (override with `MYSQL_PORT`).

## Doctrine versions (Symfony 8)

| Demo     | Doctrine ORM | Doctrine DBAL | Doctrine Migrations Bundle |
|----------|--------------|---------------|----------------------------|
| symfony8 | ^3.4         | **4.x**       | ^4.0                       |

Migrations use **CreateTablesService** with `introspectSchema()` and work with DBAL 4.x (table/column name resolution handles qualified names when needed).

## Field dictionary (optional)

**migrations/FieldDictionary/AuditFields** (if present) provides reusable MDK column definitions (timestamps, user refs). The current demo migrations do not use it; you can use it when building definitions with audit columns. Autoload: `DoctrineMigrations\\FieldDictionary\\` in `composer.json`.
