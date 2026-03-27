# Demo migration execution order (Version20250223100000-00013)

Doctrine Migrations executes classes in **alphabetical class-name order**. The `_validation` suffix ensures each validation runs **right after** its corresponding migration.

## Execution order (28 steps)

| # | Class | Type | Description |
|---|-------|------|-------------|
| 1 | Version20250223100000 | Migration | Create table `kit_item` with column `id` |
| 2 | Version20250223100000_validation | Validation | Verify `kit_item` exists with `id` and PK |
| 3 | Version20250223100001 | Migration | Create table `kit_example` (all column types) |
| 4 | Version20250223100001_validation | Validation | Verify `kit_example` exists with `id` and PK (skip if dropped in 00004) |
| 5 | Version20250223100002 | Migration | Create `kit_user` and add `user_id` column to `kit_item` |
| 6 | Version20250223100002_validation | Validation | Verify `kit_user` exists and `kit_item.user_id` exists (skip if later removed) |
| 7 | Version20250223100003 | Migration | Add FK `kit_item.user_id` -> `kit_user.id` (+ index) |
| 8 | Version20250223100003_validation | Validation | If FK `fk_kit_item_user_id` exists, OK; otherwise (for example after 00005), skip |
| 9 | Version20250223100004 | Migration | Drop table `kit_example` |
| 10 | Version20250223100004_validation | Validation | Verify `kit_example` does not exist |
| 11 | Version20250223100005 | Migration | Drop index and FK on `kit_item` (`user_id`) |
| 12 | Version20250223100005_validation | Validation | Verify FK and index no longer exist (skip on SQLite) |
| 13 | Version20250223100006 | Migration | Drop table `kit_user` (on SQLite: recreate `kit_item` without FK first) |
| 14 | Version20250223100006_validation | Validation | Verify `kit_user` does not exist |
| 15 | Version20250223100007 | Migration | Drop column `user_id` from `kit_item` |
| 16 | Version20250223100007_validation | Validation | Verify `kit_item.user_id` does not exist |
| 17 | Version20250223100008 | Migration | Rename `kit_example.col_string` -> `col_title` (no-op if table does not exist) |
| 18 | Version20250223100008_validation | Validation | If table exists: `col_title` must exist and `col_string` must not (skip if table does not exist) |
| 19 | Version20250223100009 | Migration | Modify `kit_example.col_string_nullable` length 100->200 (if table does not exist, it may create a minimal table) |
| 20 | Version20250223100009_validation | Validation | If table exists: `col_string_nullable` must exist (skip if table does not exist) |
| 21 | Version20250223100010 | Migration | Add index on `col_title` and unique on `col_guid` in `kit_example` (no-op if table/columns are missing) |
| 22 | Version20250223100010_validation | Validation | If table has `col_title` and `col_guid`: verify index and unique (skip if table or columns are missing) |
| 23 | Version20250223100011 | Migration | Create table `kit_pk_demo` (id, code, PK(id)) |
| 24 | Version20250223100011_validation | Validation | Verify `kit_pk_demo` exists with columns `id`, `code`, and PK |
| 25 | Version20250223100012 | Migration | Drop primary key on `kit_pk_demo` |
| 26 | Version20250223100012_validation | Validation | Verify `kit_pk_demo` has no PK (skip on SQLite) |
| 27 | Version20250223100013 | Migration | Add primary key on `kit_pk_demo` (column `code`) |
| 28 | Version20250223100013_validation | Validation | Verify `kit_pk_demo` has a PK (skip on SQLite; does not verify it is on `code`) |

## Summary: does each validation check the intended behavior?

| Migration | Main action | Matching validation | Match? |
|-----------|------------------|----------------------------|-------------|
| 00000 | CREATE TABLE kit_item (id, PK) | Table exists, `id` column exists, has PK | ✅ |
| 00001 | CREATE TABLE kit_example (all columns) | Table exists, `id`, PK (skip if table was dropped in 00004) | ✅ |
| 00002 | CREATE kit_user + ADD COLUMN user_id in kit_item | `kit_user` exists, `kit_item.user_id` exists (skip if already removed) | ✅ |
| 00003 | ADD FK user_id -> kit_user.id | If FK exists -> OK; if not (for example after 00005) -> skip (never throws) | ✅ (soft) |
| 00004 | DROP TABLE kit_example | Table does not exist | ✅ |
| 00005 | DROP FK + DROP INDEX in kit_item | FK and index do not exist (skip SQLite) | ✅ |
| 00006 | DROP TABLE kit_user | Table does not exist | ✅ |
| 00007 | DROP COLUMN user_id in kit_item | `user_id` column does not exist | ✅ |
| 00008 | RENAME col_string -> col_title in kit_example | If table exists: `col_title` yes, `col_string` no (skip if no table) | ✅ |
| 00009 | MODIFY col_string_nullable length 200 | If table exists: `col_string_nullable` exists (does not validate length) | ✅ |
| 00010 | ADD INDEX col_title, UNIQUE col_guid in kit_example | If table and columns exist: index on `col_title` and unique on `col_guid` (skip if missing) | ✅ |
| 00011 | CREATE TABLE kit_pk_demo (id, code, PK(id)) | Table exists, `id`, `code`, PK | ✅ |
| 00012 | DROP PRIMARY KEY kit_pk_demo | Table has no PK (skip SQLite) | ✅ |
| 00013 | ADD PRIMARY KEY (code) in kit_pk_demo | Table has PK (skip SQLite; does not verify `code` column) | ✅ (PK exists) |

## Migration dependencies (correct order)

- **00001** must run before 00004 (it creates `kit_example`, which 00004 drops).
- **00002** creates `kit_user` and `user_id` in `kit_item`; **00003** adds the FK (must run after 00002).
- **00005** removes FK and index from `kit_item`; **00006** drops `kit_user` (must run after 00005 to avoid orphan FK issues).
- **00007** removes `user_id` from `kit_item` (after 00006).
- **00008, 00009, 00010** operate on `kit_example`; in the standard flow the table was dropped in 00004, so 00008/00010 are usually no-op and 00009 may create a minimal table; validations skip when table or columns do not exist.
- **00011** creates `kit_pk_demo`; **00012** removes the PK; **00013** adds PK on `code` (fixed order).

To print this table from the demo in the console: `make list-migrations` (or `php scripts/list-migrations-order.php`).
