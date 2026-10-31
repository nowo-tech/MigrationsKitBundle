# Configuration

The bundle is configured under the root key `nowo_migrations_kit`. **`connection`** defines which Doctrine DBAL connection the **CreateTablesService** and **SchemaChecker** services use when it is injected as a service (e.g. from the container). In migrations you typically instantiate `new CreateTablesService($this->connection, ...)` with the migration’s connection, so this config is only relevant if you use the service from the container. **`collation_check`** configures the `nowo:migrations:check-collations` CI gate.

## Table of contents

- [Options](#options)
- [Example](#example)
- [Collation check](#collation-check)
- [Using another connection in a migration](#using-another-connection-in-a-migration)
- [Loading configuration](#loading-configuration)

## Options

| Option       | Type     | Default   | Description                                                                 |
|-------------|----------|-----------|-----------------------------------------------------------------------------|
| `connection` | `string` | `default` | Doctrine connection name injected into the `CreateTablesService` and `SchemaChecker` services (`doctrine.dbal.<connection>_connection`) when they are taken from the container. |
| `collation_check.connection` | `string\|null` | `null` | Connection checked by `nowo:migrations:check-collations`; `null` = `connection`. `--connection` overrides it. |
| `collation_check.expected_collation` | `string\|null` | `null` | Expected collation (`utf8mb4_unicode_ci`; on PostgreSQL e.g. `en_US.utf8` or `C`). `null` = the connection's `default_table_options.collation`. `--expected` overrides it. |
| `collation_check.expected_charset` | `string\|null` | `null` | Expected database character set / encoding, compared with `check_database` only. `null` = `default_table_options.charset`, else on MySQL the collation prefix (`utf8mb4`). |
| `collation_check.check_columns` | `bool` | `true` | Also compare string columns (MySQL / MariaDB: all; PostgreSQL: those with an explicit `COLLATE`). |
| `collation_check.check_database` | `bool` | `false` | Also compare the database default (what a table created without options gets). |
| `collation_check.ignore_tables` | `list<string>` | `[]` | Table names or `fnmatch()` patterns allowed to differ (their columns too); counted as still to convert. |
| `collation_check.ignore_columns` | `list<string>` | `[]` | `table.column` names or patterns allowed to differ (e.g. a deliberate `utf8mb4_bin` token column). |

## Example

```yaml
# config/packages/nowo_migrations_kit.yaml
nowo_migrations_kit:
    connection: default
```

For a non-default connection (e.g. `legacy`):

```yaml
nowo_migrations_kit:
    connection: legacy
```

If you omit the config file, the bundle uses `connection: default`.

## Collation check

```yaml
# config/packages/nowo_migrations_kit.yaml
nowo_migrations_kit:
    collation_check:
        expected_collation: utf8mb4_unicode_ci   # or rely on doctrine.dbal default_table_options.collation
        ignore_tables: [uptime_check_aggregate, uptime_check_result]   # large tables still to convert
        # ignore_columns: [api_token.token]
        # check_database: true
```

See [USAGE — Collation gate](USAGE.md#collation-gate-nowomigrationscheck-collations).

## Using another connection in a migration

You do not need to change this config to use a different connection inside a migration. Use the migration’s `$this->connection` when instantiating **CreateTablesService** or **SchemaChecker**:

```php
$service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
// or, for another connection (e.g. from a migration factory):
$otherConnection = $registry->getConnection('other');
$checker = new SchemaChecker($otherConnection);
```

## Loading configuration

Symfony loads configuration from:

- `config/packages/nowo_migrations_kit.yaml` (all environments)
- `config/packages/{env}/nowo_migrations_kit.yaml` (per environment)
