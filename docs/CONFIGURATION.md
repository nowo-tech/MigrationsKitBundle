# Configuration

The bundle is configured under the root key `nowo_migrations_kit`. The only option is **`connection`**, which defines which Doctrine DBAL connection the **CreateTablesService** uses when it is injected as a service (e.g. from the container). In migrations you typically instantiate `new CreateTablesService($this->connection, ...)` with the migration’s connection, so this config is only relevant if you use the service from the container.

## Options

| Option       | Type     | Default   | Description                                                                 |
|-------------|----------|-----------|-----------------------------------------------------------------------------|
| `connection` | `string` | `default` | Doctrine connection name used by the `CreateTablesService` when injected from the container. |

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
