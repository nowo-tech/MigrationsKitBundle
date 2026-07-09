# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/migrations-kit-bundle`  
**Last audited**: 2026-07-07

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoMigrationsKitBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/MigrationsKitExtension.php` | DI extension | FR-CFG-002 |
| `Migration/SchemaChecker.php` | table/column/index/FK existence checks | FR-SCHEMA-001 |
| `Migration/CreateTablesService.php` | Apply MDK definition arrays to schema | FR-MIG-001 |
| `Migration/MigrationDefinitionKeys.php` | MDK constant keys | FR-MIG-002 |
| `Migration/SchemaAssetName.php` | Asset naming helpers | FR-MIG-003 |
| `Migration/SchemaNameGenerator.php` | Generated constraint names | FR-MIG-003 |
| `Schema/Definition/SchemaDefinitionParser.php` | Parse declarative definitions | FR-MIG-004 |
| `Schema/TableSchemaHelper.php` | Table-level schema helpers | FR-SCHEMA-002 |
| `FieldDictionary/IdField.php` | Standard id column definition | FR-MIG-005 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 11 | 11 |
| YAML config | 1 | 1 |
| **Total production sources** | **12** | **12** |
