# Security

This document describes the security posture of `nowo-tech/migrations-kit-bundle`, including threat model, mitigations, and release controls.

## Scope

This bundle provides migration helpers for Doctrine DBAL and Doctrine Migrations:

- `SchemaChecker` (existence/introspection checks)
- `CreateTablesService` (declarative schema operations)
- parser/util classes for schema definitions and naming helpers

Out of scope: application-specific authentication/authorization policies and business-level input validation in host projects.

## Reporting a vulnerability

If you discover a security issue:

- **Do not** open a public GitHub issue.
- Report privately through the repository Security channel or by contacting maintainers.
- Include affected version, impact, reproduction steps, and PoC (if available).

We will acknowledge and triage the report as quickly as possible.

## Attack surface

Primary attack/input surface is developer-controlled migration definitions and schema metadata:

- declarative arrays passed to `CreateTablesService::apply()`
- table/column/index/foreign key names and options
- generated SQL emitted through Doctrine DBAL platform APIs

This bundle does not expose HTTP endpoints directly.

## Threat model and risks

Relevant risks for this bundle:

- **SQL misuse/injection risk** from malformed identifiers in custom migration definitions
- **Unsafe schema operations** (dropping wrong assets, FK ordering issues, partial failures)
- **Privilege/exposure risk** if migrations are executed with overly privileged DB users
- **DoS/operational risk** from large schema diffs or long-running migrations
- **Supply-chain risk** from vulnerable Composer dependencies
- **Secret leakage risk** via repository files or logs

Lower relevance in this bundle context (but relevant in host apps): XSS/CSRF/auth bypass.

## Controls and mitigations

- Schema operations are generated via Doctrine DBAL platform APIs rather than handcrafted SQL where possible.
- Migration operations are ordered to reduce dependency breakage (`FK -> index -> column` on drops; columns before indexes/FKs on adds).
- Idempotent existence checks reduce repeated/unsafe operations.
- Demo and CI flows validate migrations on supported platforms (SQLite/MySQL and DBAL matrix).
- Secrets are not hardcoded in bundle code; local env files are ignored.

## Cryptography and secrets

- The bundle itself does not implement custom cryptography.
- No static API keys/tokens are required by bundle runtime.
- Secret material must be managed by host applications and CI secret stores, never committed.

## Logging and sensitive data

- Bundle code should avoid logging credentials, tokens, DSNs with passwords, or sensitive query payloads.
- Diagnostic output in CI/test flows should remain technical and non-secret.

## Dependencies and update policy

- Keep dependencies current and run `composer audit` before release.
- Triage and patch vulnerable transitive dependencies quickly.
- CI must continue validating core compatibility matrix after security updates.

## Permissions and exposure

- Run migrations with least-privilege DB credentials appropriate for schema changes.
- Avoid using application superuser credentials in shared environments.
- Restrict migration execution to trusted deployment pipelines.

## Availability and limits

- Validate SQL with dry-run/write-sql flows before production execution.
- Apply migration batches in controlled windows for large schema changes.
- Ensure DB-level timeout/lock settings are aligned with migration complexity.

## Release security checklist (12.4.1)

Before tagging a release, confirm all items below:

| Item | Required confirmation |
|------|------------------------|
| `docs/SECURITY.md` | This file is updated and still accurate for current code. |
| `.env` policy | `.env` and local variants are ignored; no local secret files are committed. |
| No secrets in repository | No API keys, passwords, tokens, or private keys in tracked files/history. |
| Safe recipe/config defaults | Installation recipe and docs do not embed production secrets. |
| Input/output controls | Migration definitions and emitted SQL paths are reviewed for unsafe handling. |
| Dependencies | `composer audit` executed and findings triaged/resolved. |
| No-secret logging | Logs and docs do not expose sensitive values. |
| Cryptography/secrets | No hardcoded secrets; host app secret management remains external. |
| Permissions/exposure | Migration execution permissions and runtime context reviewed. |
| Limits/DoS | Migration plan reviewed for heavy operations and lock/timeout impact. |
| **AI security audit (REQ-SEC-004)** | Grade **Pass (good)** / risk **Low** (2026-07-29). Recorded in the Nowo monorepo `BUNDLES_SECURITY_ANALYSIS.md`. |

Record the checklist confirmation in the release PR or tag notes.

## AI security audit

| Field | Value |
| ----- | ----- |
| Date | 2026-07-29 |
| Grade | Pass (good) |
| Risk | Low |
| Method | Cursor security-review / campaign static pass (`src/`, Flex recipe, demo, SECURITY docs) |
| Open residuals | No Critical/High. Accepted Low residual: validate identifier names in custom migration definitions; no HTTP admin surface in the bundle. |

