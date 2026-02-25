# Symfony Migrations and Doctrine DBAL API — Summary

Summary of methods for **checks**, **create**, **edit**, and **delete** in migrations, and differences across DBAL versions.

---

## 1. Doctrine Migrations (migration class)

In `up(Schema $schema)` / `down(Schema $schema)` you have:

| Method / property | Use |
|-------------------|-----|
| `$this->addSql($sql)` | Adds a SQL statement to run in this migration. |
| `$this->connection` | `Doctrine\DBAL\Connection` to run SQL or get schema/platform. |
| `$this->connection->createSchemaManager()` | Gets the schema manager (DBAL 3.4+ / 4.x). |
| `$schema` (argument) | In 3.x it is the “target” schema from diff; to check actual state use `introspectSchema()`. |

To check what exists in the database before emitting SQL, use the **introspected** schema:

```php
$schemaManager = $this->connection->createSchemaManager();
$introspected  = $schemaManager->introspectSchema();
// Check with $introspected->hasTable('my_table'), etc.
```

---

## 2. Checks (does it exist?)

All checks are done against a `Schema` (usually the one returned by `introspectSchema()`).

### 2.1 Schema (`Doctrine\DBAL\Schema\Schema`)

| Method | Description | DBAL 3.x | DBAL 4.x |
|--------|-------------|----------|----------|
| `hasTable($name)` | Does the table exist? | ✓ | ✓ |
| `getTable($name)` | Gets the table (throws if it does not exist). | ✓ | ✓ |
| `getTables()` | List of tables in the schema. | ✓ | ✓ |

In DBAL 4.x names may be `Identifier`; you may need to normalize when comparing by “short” name (e.g. strip schema prefix).

### 2.2 Table (`Doctrine\DBAL\Schema\Table`)

| Method | Description | DBAL 3.x | DBAL 4.x |
|--------|-------------|----------|----------|
| `hasColumn($name)` | Does the column exist? | ✓ | ✓ |
| `getColumn($name)` | Gets the column. | ✓ | ✓ |
| `getColumns()` | List of columns. | ✓ | ✓ |
| `getIndexes()` | Indexes (incl. unique). | ✓ | ✓ |
| `getForeignKeyConstraints()` | Foreign keys on the table. | ✓ | ✓ |
| `getPrimaryKey()` / `hasPrimaryKey()` | Primary key. | ✓ | ✓ |

In 4.x, `getName()` on Table/Column/Index may return an `Identifier`; use `->getQuotedName()` or compare against the expected name for the platform.

### 2.3 SchemaManager (introspection)

To **only** check existence without loading the full schema:

| Method | Description | DBAL 3.x | DBAL 4.x |
|--------|-------------|----------|----------|
| `introspectSchema()` | Current full schema. | — (use `createSchema()`) | ✓ |
| `introspectTables()` | List of tables. | `listTables()` | ✓ |
| `introspectTableByUnquotedName($name)` | One table by name. | `listTableDetails($name)` | ✓ |

In **DBAL 3.x** you used `$connection->getSchemaManager()` (deprecated in 3.x, removed in 4.x) and methods like `listTableNames()`, `listTableColumns($table)`, `listTableIndexes($table)`, `listTableForeignKeys($table)` to check columns/indexes/FKs.

---

## 3. Create (tables, columns, indexes, FKs)

### 3.1 Generating create SQL

| Source | How to get SQL | Use |
|--------|----------------|-----|
| **Full schema** | `$schema->toSql($platform)` | Create all tables in the schema. |
| **Single table** | `$platform->getCreateTableSQL($table)` | Create one table (used by this bundle in `CreateTablesService`). |

`$platform` = `$this->connection->getDatabasePlatform()`.

### 3.2 Building Schema / Table

- **DBAL 3.x:** Mutable schema. You can use `$schema->createTable($name)` and then `$table->addColumn(...)`, `$table->addIndex(...)`, `$table->addForeignKeyConstraint(...)`. The same `Table` from `getTable()` can be modified before generating SQL.
- **DBAL 4.x:** Table is built with the **editor** pattern (immutable). Example:

  ```php
  use Doctrine\DBAL\Schema\Column;
  use Doctrine\DBAL\Schema\Table;
  use Doctrine\DBAL\Schema\PrimaryKeyConstraint;

  $table = Table::editor()
      ->setUnquotedName('user')
      ->addColumn(
          Column::editor()->setUnquotedName('id')->setTypeName('integer')->create()
      )
      ->addColumn(
          Column::editor()->setUnquotedName('name')->setTypeName('string')->setLength(255)->create()
      )
      ->addPrimaryKeyConstraint(
          PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create()
      )
      ->create();
  $schema = new Schema([$table]);
  $sqls = $schema->toSql($platform);
  ```

In this bundle, `SchemaDefinitionParser` builds the `Table` in a form compatible with the platform API (`getCreateTableSQL($table)`), for both 3.x and 4.x.

---

## 4. Edit (ALTER: add/remove columns, indexes, FKs)

You do not “edit” the introspected schema in memory and then dump to ALTER: you **compare** a “before” schema with an “after” schema and generate ALTER with the **Comparator**.

### 4.1 Comparator and SchemaDiff

| Step | DBAL 3.x | DBAL 4.x |
|------|----------|----------|
| Comparator | `$schemaManager->createComparator()` (or `new Comparator()`) | `$schemaManager->createComparator()` |
| Compare | `$comparator->compare($fromSchema, $toSchema)` | `$comparator->compareSchemas($fromSchema, $toSchema)` |
| Result | `SchemaDiff` | `SchemaDiff` |
| SQL | `$schemaDiff->toSql($platform)` | `$schemaDiff->toSql($platform)` |
| Add-only (no drops) | `$schemaDiff->toSaveSql($platform)` | `$schemaDiff->toSaveSql($platform)` |

Typical flow:

1. `$fromSchema = $schemaManager->introspectSchema()` (current state).
2. Clone and modify: `$toSchema = clone $fromSchema` and then, in 3.x, `$toSchema->getTable('x')->addColumn(...)`; in 4.x you build a new Table and replace it in the target schema.
3. `$diff = $comparator->compareSchemas($fromSchema, $toSchema);`
4. `foreach ($diff->toSql($platform) as $sql) { $this->addSql($sql); }`

### 4.2 Concrete changes (examples)

- **Add column:** In `$toSchema` the table must have the new column (in 3.x: `$table->addColumn(...)`).
- **Add index:** `$table->addIndex(...)` (3.x) or equivalent in 4.x with the builder.
- **Add FK:** `$table->addForeignKeyConstraint(...)` (3.x) or builder in 4.x.

Recommended **order** when applying changes: columns first, then indexes, then foreign keys. The comparator’s `SchemaDiff::toSql()` already tries to emit in a safe order.

### 4.3 Platform and TableDiff

For concrete ALTERs (without the comparator), some versions use `AbstractPlatform::getAlterTableSQL(TableDiff $diff)`. `TableDiff` is built from two `Table` instances (before/after) or from explicit changes. In practice, using the **Comparator** is the usual and portable approach.

---

## 5. Delete (DROP)

### 5.1 From a Schema

| Action | Method (on Schema/Table) | Use |
|--------|--------------------------|-----|
| Drop table from schema | `$schema->dropTable($name)` | When comparing with a schema that no longer has that table, the diff generates `DROP TABLE`. |
| Drop column | `$table->dropColumn($name)` | DBAL 3.x (mutable Table). In 4.x you build a schema “without” that column and compare. |
| Drop index | `$table->dropIndex($name)` | 3.x. |
| Drop FK | `$table->removeForeignKey($name)` | 3.x. |

In **DBAL 4.x**, Table is immutable: there is no `dropColumn` on an already-created Table. You build a new schema without that column/index/FK and use the Comparator to get the `SchemaDiff` and thus the corresponding `DROP`.

### 5.2 Direct drop SQL

- `$schema->toDropSql($platform)` generates SQL to drop **everything** defined in that schema (useful for “down” of migrations that created that full schema).

---

## 6. Summary by DBAL version

| Topic | DBAL 2.x | DBAL 3.x | DBAL 4.x |
|-------|----------|----------|----------|
| Schema manager | `$conn->getSchemaManager()` | `$conn->getSchemaManager()` (deprecated), then `createSchemaManager()` (3.4+) | `$conn->createSchemaManager()` |
| Current schema | `$sm->createSchema()` | `$sm->createSchema()` | `$sm->introspectSchema()` |
| Tables / columns | `$sm->listTableNames()`, `listTableColumns()` | Same + mutable Schema | `introspectTables()`, `introspectTableByUnquotedName()`; Table with editor (immutable) |
| Compare schemas | `Comparator::compare()` | `Comparator::compare()` | `createComparator()->compareSchemas()` |
| Names (Table/Column) | string | string | May be `Identifier` |

---

## 7. References

- [Schema representation (DBAL 4.4)](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.4/reference/schema-representation.html)
- [Schema manager (DBAL)](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/schema-manager.html)
- [Migration classes (Doctrine Migrations)](https://www.doctrine-project.org/projects/doctrine-migrations/en/current/reference/migration_classes.html)

In this bundle, **CreateTablesService** uses: introspected `Schema`, `hasTable`/`getTables()` for table checks, and `tableHasColumn()` (backed by `$table->hasColumn()`/`getColumns()`) for column checks; then either `AbstractPlatform::getCreateTableSQL($table)` to create tables or the Schema **Comparator** to emit ALTER TABLE (add/rename/modify column, add index, add FK). See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md) for the full list of operations and definition format.
