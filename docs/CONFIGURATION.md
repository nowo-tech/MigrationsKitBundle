# Configuration

The bundle is configured under the root key `nowo_migrations_kit`. The only option is **`connection`**, which defines which Doctrine DBAL connection the **SchemaChecker** service uses when injected (e.g. via a custom migration factory). In migrations you typically use `new SchemaChecker($this->connection)`, so this config is optional.

## Options

| Option       | Type     | Default   | Description                                                                 |
|-------------|----------|-----------|-----------------------------------------------------------------------------|
| `connection` | `string` | `default` | Doctrine connection name used by the `SchemaChecker` service when injected. |

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

You do not need to change this config to use a different connection inside a migration. Use the migration’s `$this->connection` (which is the connection the migration runs against) or create a checker for another connection with `withConnection()`:

```php
$checker = new SchemaChecker($this->connection);
// or, for another connection (e.g. from a migration factory):
$otherConnection = $registry->getConnection('other');
$checker = (new SchemaChecker($this->connection))->withConnection($otherConnection);
```

## Loading configuration

Symfony loads configuration from:

- `config/packages/nowo_migrations_kit.yaml` (all environments)
- `config/packages/{env}/nowo_migrations_kit.yaml` (per environment)
