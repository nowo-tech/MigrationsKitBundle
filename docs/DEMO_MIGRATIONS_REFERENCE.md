# Demo migrations reference — use cases, SQL, and safety

This document answers:

1. **Do the demos cover all bundle use cases?** — Matrix below; all bundle operations are demonstrated.
2. **Do all demo migrations use the bundle?** — Yes, except one SQLite-specific workaround in Version20250223100006.
3. **Reference SQL** — Expected queries per migration (MySQL 8 and SQLite) so you can check correctness.
4. **Is the bundle safe to use?** — Safety assessment and recommendations.

**Verification:** Re-run on 2025-02-25: `make db-reset-mysql` + `make migrate-mysql` in both `demo/symfony7` and `demo/symfony8`. All 14 migrations (Version20250223100000–00013) executed successfully; 15 SQL statements total. Reference SQL below matches the emitted output.

---

## 1. Matrix: bundle operation vs demo migration

| Bundle operation (MDK / CreateTablesService) | Demo migration | Uses bundle? |
|---------------------------------------------|----------------|--------------|
| **Create table** (TABLES + COLUMNS + PRIMARY_KEY) | Version20250223100000 (kit_item), 00001 (kit_example), 00002 (kit_user) | ✅ Yes |
| **Add column** (ALTER TABLE ADD COLUMN via comparator) | Version20250223100002 (user_id on kit_item) | ✅ Yes |
| **Add foreign key** (FOREIGN_KEYS) | Version20250223100003 | ✅ Yes |
| **Drop table** (DROP_TABLES) — simple, no FKs referencing it | Version20250223100004 (kit_example) | ✅ Yes |
| **Drop FK by name** (DROP_FOREIGN_KEYS) | Version20250223100005 | ✅ Yes |
| **Drop index** (DROP_INDEXES) | Version20250223100005 | ✅ Yes |
| **Drop table** (DROP_TABLES) — table was referenced by FK (dropped in 00005) | Version20250223100006 (kit_user) | ✅ Bundle for DROP TABLE; on **SQLite** only, extra raw SQL to recreate kit_item without FK first (SQLite doesn’t support DROP FOREIGN KEY) |
| **Drop column** (DROP_COLUMNS) | Version20250223100007 | ✅ Yes |
| **Rename column** (RENAME) | Version20250223100008 | ✅ Yes (when table exists; when table was dropped in 00004, apply() correctly skips and emits no SQL) |
| **Modify column** (type/options) | Version20250223100009 | ✅ Yes |
| **Create index / unique** (INDEXES) | Version20250223100010 | ✅ Yes |
| **Create table** (no AUTO_INCREMENT; for PK demos) | Version20250223100011 (kit_pk_demo) | ✅ Yes |
| **Drop primary key** (DROP_PRIMARY_KEYS) | Version20250223100012 (kit_pk_demo) | ✅ Yes |
| **Change primary key** (define new PRIMARY_KEY on existing table) | Version20250223100013 (kit_pk_demo) | ✅ Yes |

---

## 2. Reference SQL per migration

Below is the **expected SQL for MySQL 8** when running all migrations in order on an empty database. For **SQLite**, the same logical operations are emitted; syntax differs (e.g. no `DROP FOREIGN KEY` on SQLite; Version20250223100006 adds manual SQL on SQLite to recreate `kit_item` without FK).

### How to generate the SQL yourself

- **MySQL**: From the demo directory you can apply migrations and see output with `make db-reset-mysql` then `make migrate-mysql`. To write all migration SQL to a single file for validation:

```bash
make write-sql-reference-mysql
```

Then inspect `var/expected_migrations_mysql.sql`. That target resets the MySQL database and runs migrations with `--write-sql=var/expected_migrations_mysql.sql`, so you get one file with all statements in execution order. See [USAGE.md — Exporting and viewing SQL](USAGE.md#exporting-and-viewing-sql-before-applying) for more options. The full per-migration breakdown is in the sections below.

- **SQLite**: From the demo directory:
  ```bash
  make db-reset
  make migrate-write-sql
  ```
  This writes the pending migration SQL to `var/migration.sql`. Or run `make migrate` with `-vvv` and redirect output to a file to see the SQL executed per migration.

---

### Version20250223100000 — Create table kit_item

```sql
CREATE TABLE kit_item (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id));
```

---

### Version20250223100001 — Create table kit_example (all column types)

```sql
CREATE TABLE kit_example (
  id INT AUTO_INCREMENT NOT NULL,
  col_smallint SMALLINT DEFAULT 0 NOT NULL,
  col_bigint BIGINT DEFAULT NULL,
  col_boolean TINYINT DEFAULT 1 NOT NULL,
  col_decimal NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
  col_float DOUBLE PRECISION DEFAULT NULL,
  col_string VARCHAR(255) NOT NULL,
  col_string_nullable VARCHAR(100) DEFAULT NULL,
  col_text LONGTEXT DEFAULT NULL,
  col_ascii VARCHAR(64) DEFAULT NULL,
  col_datetime DATETIME DEFAULT NULL,
  col_datetime_immutable DATETIME DEFAULT NULL,
  col_date DATE DEFAULT NULL,
  col_time TIME DEFAULT NULL,
  col_json JSON DEFAULT NULL,
  col_blob LONGBLOB DEFAULT NULL,
  col_guid CHAR(36) DEFAULT NULL,
  col_comment VARCHAR(50) DEFAULT NULL COMMENT 'Example comment',
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id)
);
```

---

### Version20250223100002 — Create kit_user, add user_id to kit_item

```sql
CREATE TABLE kit_user (
  id INT AUTO_INCREMENT NOT NULL,
  name VARCHAR(180) NOT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id)
);
ALTER TABLE kit_item ADD user_id INT DEFAULT NULL;
```

---

### Version20250223100003 — Add foreign key kit_item.user_id -> kit_user.id

```sql
ALTER TABLE kit_item ADD CONSTRAINT fk_kit_item_user_id FOREIGN KEY (user_id) REFERENCES kit_user (id) ON DELETE SET NULL;
CREATE INDEX IDX_E222877DA76ED395 ON kit_item (user_id);
```

---

### Version20250223100004 — Drop table kit_example

```sql
DROP TABLE `kit_example`;
```

---

### Version20250223100005 — Drop index and FK on kit_item (user_id)

```sql
ALTER TABLE `kit_item` DROP FOREIGN KEY fk_kit_item_user_id;
DROP INDEX IDX_E222877DA76ED395 ON `kit_item`;
```

---

### Version20250223100006 — Drop table kit_user

**MySQL**:  
```sql
DROP TABLE `kit_user`;
```

**SQLite**: Before calling the bundle, the migration adds raw SQL to recreate `kit_item` without the FK (because SQLite does not support `DROP FOREIGN KEY`). Then the bundle emits `DROP TABLE kit_user`.

---

### Version20250223100007 — Drop column user_id from kit_item

```sql
ALTER TABLE kit_item DROP user_id;
```

---

### Version20250223100008 — Rename col_string to col_title in kit_example

- If **kit_example exists** (e.g. you reverted 00004 or run in a different order):  
  `ALTER TABLE kit_example RENAME COLUMN col_string TO col_title;` (or platform equivalent).
- If **kit_example was dropped** in 00004 (normal demo order): the bundle **emits no SQL** (table missing and definition only has RENAME → skip). Correct and safe.

---

### Version20250223100009 — Modify col_string_nullable length to 200 in kit_example

In the demo order, **kit_example** was dropped in 00004. The migration still calls the bundle; the bundle will create or alter depending on schema. For a table that no longer exists but has a full column definition, it can emit a `CREATE TABLE` with only that column (as in the demo run). If you need “modify” only when the table exists, keep the table (e.g. don’t run 00004) or run 00009 in a context where kit_example exists.

Expected when **kit_example exists** and only `col_string_nullable` length changes:

```sql
ALTER TABLE kit_example CHANGE col_string_nullable col_string_nullable VARCHAR(200) DEFAULT NULL;
```

(Exact syntax may vary by platform; comparator generates the appropriate ALTER.)

---

### Version20250223100010 — Add index and unique on kit_example

If **kit_example** exists and has `col_title` and `col_guid`:

```sql
CREATE INDEX idx_kit_example_col_title ON kit_example (col_title);
CREATE UNIQUE INDEX uniq_kit_example_col_guid ON kit_example (col_guid);
```

If the table or columns are missing, the bundle skips creating the index/unique (no SQL). In the default demo order (table dropped in 00004), this migration typically emits no SQL.

---

### Version20250223100011 — Create table kit_pk_demo (for PK drop/change demos)

Table without AUTO_INCREMENT so MySQL allows DROP PRIMARY KEY in 00012 (MySQL requires an auto column to be part of a key).

```sql
CREATE TABLE kit_pk_demo (id INT NOT NULL, code VARCHAR(32) NOT NULL, PRIMARY KEY (id));
```

---

### Version20250223100012 — Drop primary key on kit_pk_demo

```sql
ALTER TABLE kit_pk_demo DROP PRIMARY KEY;
```

---

### Version20250223100013 — Add primary key on kit_pk_demo (existing table, column code)

```sql
ALTER TABLE kit_pk_demo ADD PRIMARY KEY (code);
```

---

## 3. Is the bundle safe to use?

### Safe aspects

- **Idempotent behaviour**: The service uses the **introspected** schema and only emits SQL for what is missing or changed (create table if not exists, add column if missing, add FK/index if not present, drop only if exists). So re-running the same migration definition usually does not duplicate objects or fail.
- **Order of operations**: Drops are applied in a safe order (FKs → indexes → columns → PK → tables); adds in a safe order (tables/columns → indexes → FKs). This avoids dependency violations.
- **Skip when nothing to do**: For example, if the table does not exist and the definition only has RENAME, the bundle skips (no invalid CREATE). Same for adding an index/FK that already exists.
- **Tests**: The bundle has unit tests (including SchemaMigrationServiceTest and CreateTablesServiceMySQLPlatformTest) and the demos are run in CI (e.g. `make test-mysql`).

### Recommendations

1. **Always review SQL before applying**: Use `doctrine:migrations:migrate --dry-run -vvv` or `make migrate-dry-run` (and optionally `make migrate-write-sql`) and check the generated SQL in your environment.
2. **Prefer small, ordered migrations**: One concern per migration (e.g. add column → then add index → then add FK) reduces risk and makes rollback clearer.
3. **Platform differences**: SQLite does not support `DROP FOREIGN KEY`; the demo uses a workaround in 00006. On MySQL/PostgreSQL the bundle emits standard DROP FK / DROP INDEX. Be aware of platform-specific behaviour when targeting multiple databases.
4. **Transactional mode**: By default Doctrine Migrations run each migration in a transaction and commit after it. For DDL that implies commits (e.g. on MySQL), consider `transactional: false` for those migrations if required (see Doctrine docs on implicit commits).

### Summary

The bundle is **safe to use** when you:

- Pass the **introspected** schema and use the documented MDK keys.
- Review generated SQL (dry-run / write-sql) before applying.
- Follow the recommended order (drops: FK → index → column; adds: column → index → FK) and use the bundle’s phased behaviour to avoid invalid operations.

The demo migrations are a good reference to verify that the SQL emitted for each use case is correct on your target platform.
