# FrankenPHP worker mode audit (`FRANKENPHP_RESET_KERNEL` unset / false)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/migrations-kit-bundle` (`symfony-bundle`) |
| Audited revision | post-`v2.0.21` (release **2.0.22**) |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` (DI extension, configuration, `Resources/config/services.yaml`, migration helpers, schema parser, field dictionary) + PHPStan FrankenPHP classic / worker / worker-strict rulesets |
| **Verdict** | ✅ **Compatible** with FrankenPHP worker when the kernel is **not** reset between requests (`FRANKENPHP_RESET_KERNEL` unset or `0`). The bundle registers no HTTP-runtime services; helpers are `final readonly` (or pure static) and hold no per-request state. Safe under scenario B (no reliance on `services_resetter`). |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. Symfony Runtime default is **kernel reused** (`FRANKENPHP_RESET_KERNEL` unset/false). Setting `FRANKENPHP_RESET_KERNEL=1` clones the application after each request (escape hatch; lower throughput). This audit targets the **strict** default:

- **A — kernel not reset, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — kernel not reset, no service reset relied upon:** nothing in this bundle needs `kernel.reset`; there is no per-request mutable state in shared services.

A bundle that is safe under **B** is safe under **A**, under `FRANKENPHP_RESET_KERNEL=1`, and under classic mode / PHP-FPM.

## Why HTTP worker impact is minimal

- `src/Resources/config/services.yaml` only declares `_defaults`. No service, resource or alias is registered by the shipped config.
- `MigrationsKitExtension` sets the `nowo_migrations_kit.connection` parameter and only wires `CreateTablesService` when that class is already defined (host app optional registration). With the shipped YAML this never runs.
- `NowoMigrationsKitBundle` has no `boot()`, compiler pass, listener or subscriber. At HTTP runtime the kernel only loads the bundle and extension classes (compile-time / no request state).
- `CreateTablesService`, `SchemaChecker` and `SchemaDefinitionParser` are intended for `new` inside Doctrine `AbstractMigration` (CLI `doctrine:migrations:migrate`). They do not run in HTTP requests unless the application wires them itself — and even then they remain B-safe (see below).

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ N/A / ✅ | No shipped container services; helpers are `final readonly` or property-free |
| Static properties / `static` locals | ✅ | None mutable; only pure static helpers (`SchemaAssetName`, `SchemaNameGenerator`, `TableSchemaHelper`, `IdField`) and closures |
| `ResetInterface` / `kernel.reset` | ✅ N/A | Nothing to reset |
| Request / user / locale in services | ✅ N/A | No HTTP-related dependencies |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used |
| Doctrine / EntityManager | ✅ N/A | DBAL `Connection` from the caller; fresh schema manager per call; no ORM |
| Output, headers, `exit`, shutdown functions | ✅ | None; `apply()` returns SQL strings |
| Resources (files, sockets, cURL) held open | ✅ | None |
| Memory growth across requests | ✅ | No caches or accumulating arrays on services |
| Blocking I/O and timeouts | ✅ N/A | Only schema introspection on the caller's connection when helpers run |
| Third-party static state | ✅ | Doctrine DBAL only; no process-wide configuration mutated |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker-strict.neon` (includes worker) in `phpstan.neon.dist` |

## Services / classes reviewed

| Class / service | Shared | Mutable state | Scenario A | Scenario B |
|-----------------|--------|---------------|------------|------------|
| (none registered by shipped `services.yaml`) | — | — | N/A | N/A |
| `Migration\CreateTablesService` | optional host service or `new` | none (`final readonly`: `Connection`, `SchemaDefinitionParser`) | ✅ | ✅ |
| `Migration\SchemaChecker` | optional / `new` | none (`final readonly`: `Connection`) | ✅ | ✅ |
| `Schema\Definition\SchemaDefinitionParser` | optional / `new` | none (no properties; reflection not cached) | ✅ | ✅ |
| Static helpers (`SchemaAssetName`, `SchemaNameGenerator`, `TableSchemaHelper`, `MigrationDefinitionKeys`, `FieldDictionary\IdField`) | — | none | ✅ | ✅ |
| `NowoMigrationsKitBundle`, `MigrationsKitExtension`, `Configuration` | compile-time | none | ✅ | ✅ |

## Findings

No worker-mode defects. Scenario B is satisfied.

### W-01 — `connection` option has no effect with the shipped service config (Info)

- **Where:** `MigrationsKitExtension` and `services.yaml` `_defaults` only.
- **Worker impact:** none. `CreateTablesService` is not registered by default, so `nowo_migrations_kit.connection` is only stored as a parameter.
- **Recommendation:** if the host registers `CreateTablesService` as a shared service, keep it `readonly` / inject `Connection` only; pass a fresh `Schema` from `introspectSchema()` on every `apply()` call (do not store schema on the service). Out of scope for worker remediation.

## Usage recommendations in worker mode

- No special configuration is required for worker / `FRANKENPHP_RESET_KERNEL` unset or `0`.
- Prefer running migrations from the CLI (`doctrine:migrations:migrate`), not from an HTTP controller in the worker.
- If the application registers `CreateTablesService` or `SchemaChecker` as container services, they remain safe to share: introspect schema per call; do not keep a `Schema` instance in a service property.
- Demo: `demo/symfony8` defaults to `FRANKENPHP_MODE=worker` (Caddyfile `worker { … }` block). Classic mode uses `Caddyfile.dev`.

## Re-audit triggers

Re-run this audit when a change: registers services in `services.yaml` (for example `CreateTablesService` with the configured connection), adds a listener, subscriber, compiler pass or `boot()` logic, adds a cache (introspected schemas, reflection, SQL), or adds static / request-scoped mutable state.
