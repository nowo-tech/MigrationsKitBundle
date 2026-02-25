# Migrations Kit Bundle – Demo (Symfony 8)

This demo uses **nowo-tech/migrations-kit-bundle** via a path repository (`../..`). All migrations use **CreateTablesService** and the MDK definition format:

- **Version20250223100000** – Create table `kit_item` (id column only).
- **Version20250223100001** – Create table `kit_example` (all supported column types and options).
- **Version20250223100002** – Create table `kit_user` and add `user_id` to `kit_item`.
- **Version20250223100003** – Add foreign key `kit_item.user_id` → `kit_user.id`.
- **Version20250223100004** – Drop table `kit_example` (simple drop, no dependencies).
- **Version20250223100005** – Drop index and FK on `kit_item` (shortcuts `drop_indexes`, `drop_foreign_keys`).
- **Version20250223100006** – Drop table `kit_user` (Phase 1 drops FK; on SQLite the migration recreates `kit_item` without FK first).
- **Version20250223100007** – Drop column via `DROP_COLUMNS`.
- **Version20250223100008** – Rename column (`RENAME`).
- **Version20250223100009** – Modify column (type/options).
- **Version20250223100010** – Add indexes/unique (`INDEXES`).

See [DECLARATIVE_SCHEMA.md](../../docs/DECLARATIVE_SCHEMA.md) for the definition format and [DEMO_MIGRATIONS_REFERENCE.md](../../docs/DEMO_MIGRATIONS_REFERENCE.md) for the use-cases matrix. For MySQL 8 troubleshooting (schema sync status, migration order), see [docs/MIGRATIONS_MYSQL.md](docs/MIGRATIONS_MYSQL.md).

## Run the demo

**With Docker (recommended):**

```bash
make up        # Start container. Does NOT run migrations.
make migrate   # Run migrations (SQLite by default)
```

Open http://localhost:8008 (or `$PORT`). The bundle is mounted at `/var/migrations-kit-bundle` in the container.

**MySQL 8:** Set `DATABASE_URL="mysql://app:app@mysql:3306/demo"` in `.env`, then `make up-mysql` and `make migrate-mysql`. See [docs/MIGRATIONS_MYSQL.md](docs/MIGRATIONS_MYSQL.md).

**Without Docker**, from this directory:

```bash
composer install
composer migrate
```

For local runs without Docker, set the path repo in `composer.json` to `../..` instead of `/var/migrations-kit-bundle`.

**From the bundle root:** `make demo-up-symfony8` then `make demo-migrate-symfony8`.

Database: SQLite at `var/data.db` by default (see `.env`). Compatible with MySQL and PostgreSQL; see `scripts/ensure-database.php`.
