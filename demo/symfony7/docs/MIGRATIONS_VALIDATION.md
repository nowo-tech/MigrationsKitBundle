# Migrations validation — Demo Symfony 7 (MySQL 8)

Run: `make db-reset-mysql` + `make migrate-mysql` with `-vvv` output. Empty database, all migrations in order.

| Migration | Condition | SQL executed | Correct? |
|-----------|-----------|---------------|----------|
| **Version20250223100000** | Create table kit_item (id, PK) | `CREATE TABLE kit_item (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB` | ✅ OK |
| **Version20250223100001** | Create table kit_example (all column types) | `CREATE TABLE kit_example (id INT AUTO_INCREMENT NOT NULL, col_smallint SMALLINT ..., col_boolean TINYINT(1) ..., PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB` | ✅ OK |
| **Version20250223100002** | Create kit_user and add user_id to kit_item | `CREATE TABLE kit_user (...);` `ALTER TABLE kit_item ADD user_id INT DEFAULT NULL` | ✅ OK |
| **Version20250223100003** | Add FK kit_item.user_id → kit_user.id | `ALTER TABLE kit_item ADD CONSTRAINT fk_kit_item_user_id FOREIGN KEY (user_id) REFERENCES kit_user (id) ON DELETE SET NULL` `CREATE INDEX IDX_E222877DA76ED395 ON kit_item (user_id)` | ✅ OK |
| **Version20250223100004** | Drop table kit_example (no dependencies) | `DROP TABLE kit_example` | ✅ OK |
| **Version20250223100005** | Drop FK and index on kit_item (user_id) | `ALTER TABLE kit_item DROP FOREIGN KEY fk_kit_item_user_id` `DROP INDEX IDX_E222877DA76ED395 ON kit_item` | ✅ OK |
| **Version20250223100006** | Drop table kit_user | `DROP TABLE kit_user` | ✅ OK |
| **Version20250223100007** | Drop column user_id from kit_item | `ALTER TABLE kit_item DROP user_id` | ✅ OK |
| **Version20250223100008** | Rename col_string → col_title in kit_example | *(No SQL: table kit_example was dropped in 00004; bundle correctly skips)* | ✅ OK |
| **Version20250223100009** | Modify col_string_nullable to VARCHAR(200) in kit_example | `CREATE TABLE kit_example (col_string_nullable VARCHAR(200) DEFAULT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB` | ✅ OK |
| **Version20250223100010** | Add index and unique on kit_example | *(No SQL: table lacks col_title/col_guid; bundle skips)* | ✅ OK |
| **Version20250223100011** | Create table kit_pk_demo (no AUTO_INCREMENT, for PK demos) | `CREATE TABLE kit_pk_demo (id INT NOT NULL, code VARCHAR(32) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB` | ✅ OK |
| **Version20250223100012** | Drop primary key on kit_pk_demo | `ALTER TABLE \`kit_pk_demo\` DROP PRIMARY KEY` | ✅ OK |
| **Version20250223100013** | Add primary key on kit_pk_demo (column code) | `ALTER TABLE kit_pk_demo ADD PRIMARY KEY (code)` | ✅ OK |

**Summary:** 14 migrations executed, 15 SQL statements. All operations match the expected demo flow (standard order, MySQL 8 database). Format differences vs Symfony 8: DBAL/Symfony 7 emits `DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB` on CREATE TABLE, and in 00004/00005 does not quote names with backticks in this run.

*Full log in `docs/migrate_log.txt`.*
