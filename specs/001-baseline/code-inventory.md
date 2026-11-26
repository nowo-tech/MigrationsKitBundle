# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/migrations-kit-bundle`  
**Last audited**: 2026-11-26 (`git ls-files src | wc -l` → 35)

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Collation/CollationFoldingProbe.php` | Asks the server whether a collation folds case/accents (2.9) | FR-KEY-001 |
| `Collation/ExactKeyTableFinding.php` | Table-default finding for a table holding a declared key column (VO, 2.9) | FR-KEY-001 |
| `Collation/TableCollationReader.php` | Reads `information_schema.TABLES.TABLE_COLLATION` for both gates (2.9) | FR-COLL-001, FR-KEY-001 |
| `Collation/UniformCollation.php` | The one uniform text collation + charset, resolved once (override, config, DBAL default) (2.9) | FR-COLL-001, FR-KEY-001 |
| `NowoMigrationsKitBundle.php` | Bundle entry | FR-BUNDLE-001, FR-WORKER-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/MigrationsKitExtension.php` | DI extension | FR-CFG-002, FR-WORKER-001 |
| `Migration/SchemaChecker.php` | table/column/index/FK existence checks | FR-SCHEMA-001, FR-WORKER-001 |
| `Migration/CreateTablesService.php` | Apply MDK definition arrays to schema | FR-MIG-001, FR-WORKER-001 |
| `Migration/MigrationDefinitionKeys.php` | MDK constant keys | FR-MIG-002 |
| `Migration/SchemaAssetName.php` | Asset naming helpers | FR-MIG-003, FR-WORKER-001 |
| `Migration/SchemaNameGenerator.php` | Generated constraint names | FR-MIG-003, FR-WORKER-001 |
| `Schema/DefaultTableOptions.php` | Table options of `doctrine.dbal.default_table_options` read from the connection (2.9) | FR-SCHEMA-003, FR-KEY-001 |
| `Schema/Definition/SchemaDefinitionParser.php` | Parse declarative definitions | FR-MIG-004, FR-WORKER-001 |
| `Schema/TableSchemaHelper.php` | Table-level schema helpers | FR-SCHEMA-002, FR-WORKER-001 |
| `FieldDictionary/IdField.php` | Standard id column definition | FR-MIG-005, FR-WORKER-001 |
| `Collation/CollationChecker.php` | Database/table/column collation check | FR-COLL-001, FR-WORKER-001 |
| `Collation/CollationMismatch.php` | One mismatching object (VO) | FR-COLL-001 |
| `Collation/CollationReport.php` | Collation check result | FR-COLL-001 |
| `Collation/ExactCollationSupport.php` | Binary NO PAD collations offered by the server | FR-KEY-001 |
| `Collation/ExactKeyChecker.php` | Identity / access-key column gate | FR-KEY-001, FR-WORKER-001 |
| `Collation/ExactKeyFinding.php` | One key-column finding (VO) | FR-KEY-001 |
| `Collation/ExactKeyReport.php` | Key-column check result | FR-KEY-001 |
| `Command/CheckCollationsCommand.php` | `nowo:migrations:check-collations` CI gate | FR-COLL-001 |
| `Command/CheckKeyCollationsCommand.php` | `nowo:migrations:check-key-collations` CI gate | FR-KEY-001 |
| `Command/CheckMigrationVersionsCommand.php` | `nowo:migrations:check-versions` CI gate | FR-VER-001 |
| `DependencyInjection/Compiler/ExactKeyColumnsPass.php` | Collects bundles' key-column declarations | FR-KEY-001, FR-CFG-002 |
| `Test/ExactKeyColumnsTestCase.php` | Per-bundle REQ-SEC-006 test case | FR-KEY-001 |
| `Test/MonotonicMigrationVersionsTrait.php` | Kernel-free PHPUnit helper | FR-VER-001 |
| `Versions/FixFailedException.php` | `--fix` failure (atomic rename) | FR-VER-001 |
| `Versions/GitMigrationFiles.php` | Versions in the tree and at a git ref (bounded process) | FR-VER-001, FR-WORKER-001 |
| `Versions/MigrationVersions.php` | Version ordering rules | FR-VER-001 |
| `Versions/MonotonicVersionsChecker.php` | Monotonic versions check + fix | FR-VER-001, FR-WORKER-001 |
| `Versions/MonotonicVersionsReport.php` | Per-directory result | FR-VER-001 |

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
| PHP classes | 34 | 34 |
| YAML config | 1 | 1 |
| **Total production sources** | **35** | **35** |
