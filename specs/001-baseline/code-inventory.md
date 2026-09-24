# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/migrations-kit-bundle`  
**Last audited**: 2026-09-24

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoMigrationsKitBundle.php` | Bundle entry | FR-BUNDLE-001, FR-WORKER-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/MigrationsKitExtension.php` | DI extension | FR-CFG-002, FR-WORKER-001 |
| `Migration/SchemaChecker.php` | table/column/index/FK existence checks | FR-SCHEMA-001, FR-WORKER-001 |
| `Migration/CreateTablesService.php` | Apply MDK definition arrays to schema | FR-MIG-001, FR-WORKER-001 |
| `Migration/MigrationDefinitionKeys.php` | MDK constant keys | FR-MIG-002 |
| `Migration/SchemaAssetName.php` | Asset naming helpers | FR-MIG-003, FR-WORKER-001 |
| `Migration/SchemaNameGenerator.php` | Generated constraint names | FR-MIG-003, FR-WORKER-001 |
| `Schema/Definition/SchemaDefinitionParser.php` | Parse declarative definitions | FR-MIG-004, FR-WORKER-001 |
| `Schema/TableSchemaHelper.php` | Table-level schema helpers | FR-SCHEMA-002, FR-WORKER-001 |
| `FieldDictionary/IdField.php` | Standard id column definition | FR-MIG-005, FR-WORKER-001 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001, FR-WORKER-001 |

## Docs / QA (worker)

| Artifact | Requirement IDs |
| --- | --- |
| `docs/FRANKENPHP-WORKER-AUDIT.md` | FR-WORKER-003 |
| `phpstan.neon.dist` (FrankenPHP rulesets) | FR-WORKER-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| PHP classes | 11 | 11 |
| YAML config | 1 | 1 |
| **Total production sources** | **12** | **12** |
