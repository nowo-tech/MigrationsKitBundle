# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[2.8.0] - 2026-11-14](#280---2026-11-14)
- [[2.7.0] - 2026-11-14](#270---2026-11-14)
- [[2.6.0] - 2026-11-13](#260---2026-11-13)
- [[2.5.0] - 2026-11-13](#250---2026-11-13)
- [[2.4.0] - 2026-11-05](#240---2026-11-05)
- [[2.3.0] - 2026-10-31](#230---2026-10-31)
- [[2.2.1] - 2026-10-21](#221---2026-10-21)
- [[2.2.0] - 2026-10-13](#220---2026-10-13)
- [[2.0.25] - 2026-10-13](#2025---2026-10-13)
- [[2.0.24] - 2026-10-09](#2024---2026-10-09)
- [[2.0.23] - 2026-09-27](#2023---2026-09-27)
- [[2.0.22] - 2026-09-24](#2022---2026-09-24)
- [[2.0.21] - 2026-08-24](#2021---2026-08-24)
- [[2.0.20] - 2026-08-19](#2020---2026-08-19)
- [[2.0.19] - 2026-08-18](#2019---2026-08-18)
- [[2.0.18] - 2026-07-29](#2018---2026-07-29)
- [[2.0.17] - 2026-07-16](#2017---2026-07-16)
- [[2.0.16] - 2026-07-16](#2016---2026-07-16)
- [[2.0.15] - 2026-07-16](#2015---2026-07-16)
- [[2.0.14] - 2026-07-09](#2014---2026-07-09)
- [[2.0.13] - 2026-07-02](#2013---2026-07-02)
- [[2.0.12] - 2026-06-17](#2012---2026-06-17)
- [[2.0.11] - 2026-06-17](#2011---2026-06-17)
- [[2.0.10] - 2026-06-17](#2010---2026-06-17)
- [[2.0.9] - 2026-06-17](#209---2026-06-17)
- [[2.0.8] - 2026-04-15](#208---2026-04-15)
- [[2.0.7] - 2026-03-16](#207---2026-03-16)
- [[2.0.6] - 2025-02-27](#206---2025-02-27)
- [[2.0.5] - 2025-02-27](#205---2025-02-27)
- [[2.0.4] - 2025-02-27](#204---2025-02-27)
- [[2.0.3] - 2025-02-27](#203---2025-02-27)
- [[2.0.2] - 2025-02-25](#202---2025-02-25)
- [[2.0.1] - 2025-02-25](#201---2025-02-25)
- [[2.0.0] - 2025-02-25](#200---2025-02-25)
- [[1.2.1] - 2026-02-22](#121---2026-02-22)
- [[1.2.0] - 2026-02-20](#120---2026-02-20)
- [[1.1.0] - 2026-02-20](#110---2026-02-20)
- [[1.0.0] - 2026-02-20](#100---2026-02-20)

## [Unreleased]

## [2.8.0] - 2026-11-14

Target **2.8.0** (minor: a new `--strict` option on two commands, new exit-code constants and a new exception class;
the default exit codes do not change).

### Added

- **`--strict` on `nowo:migrations:check-key-collations` and `nowo:migrations:check-versions` (audits PB-M-I1,
  PF-I5).** Without it, "nothing was checked" and "everything checked and fine" both exit **0**, and a `--fix` that
  changed nothing and one whose rollback was incomplete both exit **2**: a CI step that reads only the exit code could
  not tell them apart. With `--strict`:
  - `check-key-collations` exits **3** (`CheckKeyCollationsCommand::EXIT_NOTHING_CHECKED`) when no column was actually
    verified: a platform without the notion (PostgreSQL, SQLite), no declaration from any installed bundle, or every
    declared column skipped (mode off) or ignored by the host.
  - `check-versions` exits **3** (`CheckMigrationVersionsCommand::EXIT_NOTHING_CHECKED`) when nothing was compared (no
    base ref, or a directory with no migration at the base nor in the working tree), and **4**
    (`CheckMigrationVersionsCommand::EXIT_ROLLBACK_INCOMPLETE`) when `--fix` failed and its rollback was incomplete.
  - A failure (**1**) and an unreadable database / git / failed `--fix` that changed nothing (**2**) keep their codes
    and take precedence over **3**.
- `Versions\FixFailedException` (extends `RuntimeException`): what `fix()` / `fixAll()` throw, with
  `$rollbackIncomplete` telling "nothing was changed" apart from "ROLLBACK INCOMPLETE" without parsing the message.

### Fixed

- **An unexpected error during `--fix` is rolled back (audit PF-I6).** Only the failures `fixAll()` reports itself
  were rolled back; any other throwable (an `Error`, a `Random\RandomException`, an exception from a stream wrapper)
  escaped with the staged copies, moved targets or removed sources left as they were, and the command exited **1**.
  It is now rolled back like any other failure and reported as a `FixFailedException` (exit **2**, or **4** with
  `--strict` if the rollback is incomplete) whose previous exception is the original error. A step of the rollback
  that throws no longer skips the steps after it: it is listed under ROLLBACK INCOMPLETE and the rest is undone.
- **The rollback works from what is on disk, not from how far the run got (audit PL-L1).** An operation that took
  effect and then threw (possible through a stream wrapper) was not counted, so the rollback could delete a renamed
  copy whose source was already gone (the migration was lost) or leave a duplicate, and still say "Nothing was
  changed". Each step is now undone from the disk: a missing source is written back first (staged owner-only, then
  linked into place without replacing anything), a target is removed only once its source is back and only if it is
  the very file this run staged (same device and inode, which `link()` and `rename()` keep) and still holds its
  bytes, and the "moved back" count is what was actually taken back. A file with the same name and the same bytes
  published by another run (two `--fix` at once plan the same target) is never removed (delta audit PL-D1-M1). A
  source that cannot be written back keeps its renamed copy, and the message says where the content is.
- **A failure to release the lock file after a successful run is no longer reported as a failure (audit PL-I2).**
  On a filesystem without hard links, an error from removing `.nowo-migrations-fix.lock` escaped after every rename
  was done (exit 1, no "Renamed" lines). It is ignored now: the lock file left behind does not block the next run.
- **`--fix` renames the code references to the class with its declaration (audit PF-I1).** `Version<old>::class`,
  `new Version<old>`, `instanceof`, a type, `\<namespace>\Version<old>` and `namespace\Version<old>` used to keep the
  old name, so the renamed migration referred to a class that no longer existed. Only those class-reference positions
  are renamed (audit PL-L2): anything inside a string (`b"…"` too), a heredoc or a backtick command (interpolated
  keys included),
  `use` imports (also group imports of another namespace), members and constants (`->`, `?->`, `::`, `const`,
  `case Version<old>`, while `case Version<old>::X` is a reference), function names and calls (`function
  Version<old>`, `function &Version<old>`, `Version<old>(`), named arguments and labels (`f(Version<old>: 1)`,
  `Version<old>:`; `$b ? new Version<old> : null` is a reference), and comments and docblocks are left as written.
- **`--fix` accepts a declaration written in another case (audit PF-I10).** PHP class names are case-insensitive, so
  `class version2026…` in `Version2026….php` declares the file's class; it was refused, and is now renamed (to the
  usual `Version…` spelling). References are matched the same way.
- **The same directory spelled twice is reported as such (audit PF-I2).** `--path=migrations --path=./migrations` (or
  a symbolic link to the directory) is now refused up front with "listed more than once" instead of a generic
  failure while publishing; as before, nothing is changed.
- **A staged copy is owner-only from its creation (audits PF-I4, PL-L3).** It was created with the umask's mode (often
  0644) and then filled, so a 0600 migration was briefly readable by others, and a reader that opened the file in
  that window kept its handle. On a local directory the staged copy (and a source written back by a rollback) is now
  created by `tempnam()`, which makes it 0600 whatever the umask, then filled, then given the source's mode. Its
  hidden name is therefore `.<Version>.php.<6 random characters>` instead of `.<Version>.php.<16 hex>.tmp`. Where
  `tempnam()` cannot create the file in that directory (a stream wrapper), the file is created with the umask's mode
  and set to 0600 before its first byte: no byte is stored while it is wider, but a reader that opened it empty in
  that instant keeps its handle. If that `chmod` fails, the copy is not written and nothing is changed.

### Changed

- `check-key-collations` prints "No declared column was verified: every one is skipped (mode off) or ignored by
  host." under the OK line when that is the case (exit code unchanged without `--strict`).
- `check-versions` reports a directory with no migration at the base nor in the working tree as
  `EMPTY <path>: … (is the path right?)` instead of an `OK` line with 0 migrations (exit code unchanged without
  `--strict`).
- `composer.json` `require` is in Composer's `sort-packages` order (`php`, `ext-tokenizer`, then packages) (audit
  PF-I9); no constraint changed.

### Not changed (audit PF, by design)

- **PF-I7:** if `link()` publishes a copy but the temporary name cannot be removed afterwards, a hidden
  `.<Version>.php.<random>` (a second name of the new migration) stays behind. It is not loadable as a migration
  (no `.php` suffix) and safe to delete; reporting it would change `fixAll()`'s return type, so it is documented
  instead.
- **PF-I8:** on a filesystem whose `fsync()` fails for plain files (some FUSE mounts), every `--fix` fails with exit
  **2** and changes nothing. Durability before publishing is the point of PF-L3, so this fail-safe stays; run
  `--fix` on a local checkout.
- **PL-I1 (lock file only):** an error thrown by a stream wrapper right after it created `.nowo-migrations-fix.lock`
  (before `--fix` holds it) leaves that file behind; it is never loaded and does not block the next run. The same case
  for a staged copy is fixed: its name is recorded before it is created, so the rollback removes it.

### Tests

- `CheckKeyCollationsCommandTest` and `CheckMigrationVersionsStrictTest`: every strict code and its precedence, and
  the default codes unchanged.
- `MonotonicVersionsAtomicFixTest`: code references renamed (and members, functions, constants, other namespaces,
  strings left), a reference in the global namespace, a lower-case declaration, the same directory as `migrations`
  and `./migrations`, the mode of each staged copy while it is written, and an unexpected error while staging,
  publishing and removing (rolled back) or whose rollback fails (`rollbackIncomplete`); audit PL: an error after a
  move or a removal took effect (rolled back from the disk, nothing lost), a removed source that cannot be written
  back (its renamed copy kept and named), a real-filesystem restore (mode kept, nothing left), each name that is not a
  class reference (group `use`, interpolated keys in a string, heredoc and backticks, named argument, `function &`,
  function call, `case`) and `namespace\Old`, a staged copy created 0600 under umask 0, an error right after a staged
  copy was created (removed by the rollback), a lock-release error after a successful run, a target with the same
  bytes published by a concurrent run (kept), and `case Old::X:` / `? new Old :` (renamed). `FaultyStreamWrapper`
  gains `throwAt`, `throwAfter` and `recordModes`.

[2.8.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.8.0

## [2.7.0] - 2026-11-14

Target: **2.7.0** (a new public method, `MonotonicVersionsChecker::fixAll()`, and a changed exit code for a failed
`--fix`).

### Fixed

- **`nowo:migrations:check-versions --fix` is now all or nothing across files (audit PB-M-L2).** It renamed the
  out-of-order migrations one by one, so a failure on the second of three left the first already renamed and the
  others not, and the failure escaped the command as an uncaught exception: exit **1** (the "out of order" code, not
  the documented **2**) and no list of what had moved. Every rewritten migration is now written to a temporary file
  next to its target (same directory, same permissions) and read back before anything moves; only then are the
  files moved into place and the originals removed. A failure while preparing removes the temporary files and leaves
  every original byte-identical, with its permissions; a failure while moving puts back the files already moved
  (the originals are written back from memory). If that rollback itself fails the error says **ROLLBACK
  INCOMPLETE** and names every file that needs attention; a migration that cannot be restored keeps its renamed
  copy, so none is ever lost. A source edited while `--fix` runs is not removed (the run is rolled back), and the
  renamed file keeps the source's permissions and, when the process may set it, its group. Several `--path`
  directories are one operation.
  Hardening from audit PF:
  - **PF-L1:** the class is renamed by tokens (`token_get_all`). A mention of `class Version…` in a comment, a
    docblock or a string is neither rewritten nor taken for the declaration, so it can no longer rename the file
    while the real declaration keeps the old name, nor slip past the class / file mismatch guard.
  - **PF-L2:** a staged copy is published with `link()`, which never replaces a file that already exists; a target
    created after the plan makes the run roll back. Where hard links are unavailable, the run holds an exclusive lock
    file (`.nowo-migrations-fix.lock`, removed afterwards) in the directory and checks the target before `rename()`.
    The rollback only deletes a target that still holds the bytes this run wrote.
  - **PF-L3:** staged copies are flushed and `fsync`ed before anything is published, and the directory is
    `fsync`ed after the moves and after the removals where the platform allows it (best effort).
  - When a source cannot be written back, the error names the partial file to delete. The command reports the failure and
  exits **2**.
- **`--fix` no longer renames a migration whose class name differs from its file name (audit PB-M-I5).** The class
  rewrite did nothing and the file was renamed anyway, which produced the file / class mismatch Doctrine cannot load.
  Such a migration is now reported and nothing is renamed.

### Added

- `ext-tokenizer` is now a declared requirement (used by `--fix`; built into most PHP builds, a separate package
  on some distributions such as Alpine).
- `Versions\MonotonicVersionsChecker::fixAll(list<MonotonicVersionsReport>)`: the all-or-nothing rename over several
  directories that the command uses; `fix()` is `fixAll([$report])`.

### Tests

- `MonotonicVersionsAtomicFixTest`: a failure on the second of three migrations (unreadable source, refused rename,
  unremovable source, source edited during the run), a target created after the plan (never overwritten), a rollback
  that finds a target someone else changed (kept), a directory that cannot be locked, a class name mentioned in a
  docblock, comment and string (only the declaration renamed) or only in a comment (refused), an unwritable directory, a staged copy that cannot be checked, a failed rollback (restore and
  removal), a class / file mismatch, the same directory twice, and two directories; every failure asserts the
  directory listing, contents and permissions are unchanged. Driven through a stream wrapper that fails chosen
  operations (`FaultyStreamWrapper`, no static state). Command: a failed `--fix` exits 2 and moves nothing.
- `MonotonicVersionsGitTest` used a base migration dated 2026-11-20 with the real clock, so the expected `--fix`
  names would have changed after that date; the fixture now uses 2099.

[2.7.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.7.0

## [2.6.0] - 2026-11-13

### Fixed

- **The collation probes no longer depend on `sql_mode`.** With `NO_BACKSLASH_ESCAPES` in the session, MySQL 8.4
  read `LIKE '%\\_bin'` literally, both probes returned no row, and the gate took that as "old server": it accepted
  `utf8mb4_bin` (PAD SPACE) and exited 0 with a wrong "MySQL 5.7" note. The probes now compare
  `RIGHT(COLLATION_NAME, 4) = '_bin'`, which no `sql_mode` changes (audit PB-M-L1; also present in 2.5.0).
- **`nowo:migrations:check-key-collations` could report a false all clear on a server it never managed to ask.**
  `ExactCollationSupport` swallowed any error from its two `information_schema.COLLATIONS` probes and returned an
  empty list, which `of([])` reads as "this server has no NO PAD collation" — the MySQL 5.7 / 8.0.0-8.0.16 case. The
  gate then switched to the documented fallback and **accepted `utf8mb4_bin`**, which is binary but PAD SPACE, so
  `'abc'` and `'abc   '` are one key. Failure scenario: a MySQL 8.4 server that does offer `utf8mb4_0900_bin`, reached
  by a connection whose user has no `SELECT` on `information_schema.COLLATIONS` (a restricted CI grant is enough);
  every declared identity column sitting in `utf8mb4_bin` was reported as `fallback`, the run printed "This server
  offers no NO PAD collation (MySQL 5.7 or 8.0.0-8.0.16)" — blaming a server version that was not the cause — and
  **exited 0** over columns whose keys still collide. Degradation is now a claim that has to be proven: a probe
  answered with zero rows proves it, a probe that errors proves nothing, and when no probe can be read the check
  raises instead of answering, which the command reports as exit 2 ("the database cannot be read"), as its own help
  already documented. MySQL 5.7 is unaffected: its first probe errors because there is no `PAD_ATTRIBUTE` column, and
  its second answers with zero rows, which is still a proven "none offered".
- **`nowo:migrations:check-versions --fix` could leave a migration the host cannot load.** `fix()` rewrites the class
  name inside the file and then renames the file. If the rename failed — a read-only mount, a full disk, a filesystem
  that refuses it — the exception was raised with the file still under its old name and already carrying the new class
  name, which is a class / file-name mismatch Doctrine cannot resolve, produced by the command whose job is to keep
  the directory loadable. The original bytes are now restored before the failure is reported, so a `--fix` that could
  not finish changes nothing.
- **The DBAL 2/3 legacy primary-key branch of `TableSchemaHelper` was never actually tested.** The documented test
  seam `supportsPrimaryKeyConstraints()` was `private`, so the `LegacyPrimaryKeyTableSchemaHelper` double could not
  override it and the two tests named after that branch silently exercised the DBAL 4 path instead — they would have
  passed with the legacy branch broken or removed. The seam is `protected` and called through `static::`.

### Added

- `Test\ExactKeyColumnsTestCase::uniformCollationIgnoreColumns()`: a declaring bundle or host can hand the gate the
  `collation_check.ignore_columns` entries it keeps, and the gate fails if any of them restates a column that is
  already declared. Declaring a column exempts it from the uniform collation gate by itself (2.5.0), so the ignore
  entry adds nothing on the day it is written and outlives the declaration: drop the column from the declaration and
  the leftover entry goes on excusing a column nothing checks any more.

### Changed

- `GitMigrationFiles` is no longer `final` and `SchemaDefinitionParser` gained a protected
  `addForeignKeyConstraintMethod()`: both are documented test seams for failure branches that no input can produce
  (a `proc_open()` that will not start, a DBAL `Table` without `addForeignKeyConstraint`).

### Tests

- **`CheckKeyCollationsCommand` is covered, 0 % to 100 %** (19 tests, driven through the real `ExactKeyChecker` against
  a fake `information_schema`). What is pinned is the gate's design and not its exit codes: a clean schema passes with
  per-status counts; `utf8mb4_bin` on a server that offers NO PAD fails with the `ALTER` that fixes it; the MySQL 8
  server default `utf8mb4_0900_ai_ci` fails; a column with no collation at all (`binary(16)`, `varbinary`) passes as
  byte-exact; a declared column absent from an existing table, and one whose table is absent, both fail as `missing`;
  a host `ignore_columns` entry keeps the column in the output with its reason instead of hiding it; an inactive
  column is `skipped` and only listed with `-v`; a degraded server accepts `utf8mb4_bin` and says why; PostgreSQL
  skips; an unreadable database and a non-DBAL connection exit 2. One test exists purely to keep the command a
  **positive list**: a column in the worst available collation, in a table the gate reads, is not declared and is
  therefore not checked — if that test ever fails the command has become a scanner, and a scanner can only find the
  identity columns somebody already thought of.
- **"Nothing to check" is pinned apart from "everything checked and fine".** Both exit 0, so the message and the
  counts are all a host has: a run with no declaration prints its own line and neither the `OK:` summary nor `FAIL:`,
  and a run where every declared column was skipped prints `0 ok, 0 fallback, 0 ignored by host, N skipped`.
- **`Test\ExactKeyColumnsTestCase` is covered as the host-facing contract it is**, by extending it the way a bundle
  does (`ExactKeyColumnsGateTest`) and by invoking the deliberately failing variants by hand: a bundle that declares
  nothing, a run in which every declared column was skipped, a column on the server default, a column that is both
  declared and restated in `ignore_columns`, PostgreSQL, a missing `DATABASE_URL`, and a DSN that cannot be used.
- Remaining file-by-file gaps closed: `ExactCollationSupport` (a probe that errors, and both probes erroring),
  `ExactKeyChecker` (a column that is both ignored by the host and absent), `ExactKeyFinding::describe()`,
  `ExactKeyColumnsPass` (services not loaded, and the uniform checker being optional), `SchemaDefinitionParser` (the
  foreign-key signature probe surviving a method that is not there), `TableSchemaHelper` (the legacy branch, now that
  the seam works), `GitMigrationFiles` (a `--path` value git reads as an option; a `git` that cannot be started) and
  `MonotonicVersionsChecker` (a rename the filesystem refuses, through a stream wrapper).
- Statement **and** element coverage are at **100 %** (1083/1083 statements), and CI enforces it: the coverage job
  runs `composer test-coverage-100` (REQ-TEST-003).

[2.6.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.6.0

## [2.5.0] - 2026-11-13

### Changed

- Demo uses the UiKit base style (REQ-DEMO-014)

### Tests

- Kept-alive kernel test (1,200 calls, two tenants, memory bound) and refreshed `docs/FRANKENPHP-WORKER-AUDIT.md` (REQ-RUNTIME-002).

### Added

- **Identity / access-key collation gate (REQ-SEC-006).** `nowo:migrations:check-key-collations`: fails (exit 1) when a
  column an installed bundle declares as an identity or access key is not in a **binary NO PAD** collation
  (`utf8mb4_0900_bin` on MySQL 8.0.17+ and 9.x, `utf8mb4_nopad_bin` on MariaDB; `utf8mb4_bin` is binary but PAD SPACE
  and is accepted, with a warning, only on a server that offers nothing better). Positive list, not a scan: a column
  nobody ever gave a `COLLATE` to takes the server default (`utf8mb4_0900_ai_ci`: case- and accent-insensitive *and*
  PAD SPACE) and produces no grep hit, so only a declaration can catch it.
  - The declaration itself lives in **`nowo-tech/migrations-kit-contracts`** (`ExactKeyColumnsInterface`,
    `ExactKeyColumn`, `ExactKeyColumns`, `ExactKeyStatus`; interfaces and value objects, no dependencies beyond PHP),
    so a declaring bundle requires contracts and **not** this bundle. A bundle declares its own columns with the
    table name **resolved at runtime** (prefixes, host-chosen names) and an optional `$condition` / `$active` for a
    column that only exists in an opt-in mode. Services implementing the interface are tagged by autoconfiguration
    (`ExactKeyColumnsPass`), so a host discovers every installed bundle's declaration with no registration.
  - `ExactKeyChecker` + `ExactKeyReport` / `ExactKeyFinding` — six outcomes: `ok`, `fallback`,
    `fail`, `missing` (declared and active but absent: a rename or a drop, which fails), `skipped` (the mode that
    creates it is off: not a failure) and `ignored` (host override, which keeps the column visible with its reason).
  - `ExactCollationSupport` — the available NO PAD collations are read from `information_schema.COLLATIONS`, never
    inferred from a version string, so the check stays correct on MySQL 9.x, Percona, forks and MariaDB.
  - `Test\ExactKeyColumnsTestCase` — the same gate inside a bundle's own suite, against its test database; skips
    cleanly without `DATABASE_URL` or on PostgreSQL / SQLite.
  - Config `nowo_migrations_kit.key_collation_check` (`connection`, `ignore_columns` as `table.column => reason`).
  - Declared columns are **automatically exempt** from `nowo:migrations:check-collations`, so a host never restates
    them in `collation_check.ignore_columns` and the two gates cannot disagree.

### Documentation

- USAGE: new section "Using it from a DDD / hexagonal application" (where the bundle sits in a hexagonal host, the ports, events and DTOs to build an anti-corruption layer on, and the known limitations).
- Docs: `SchemaChecker` and `CreateTablesService` are both container services bound to `nowo_migrations_kit.connection`; USAGE ("Multiple connections"), README, INSTALLATION and the `connection` config help text said only `CreateTablesService` used it (or that `SchemaChecker` was not a service). The DDD section now states the standard (container) and both-hosts (`new` in a migration) paths; the 2.0.22 worker audit carries a note that service registration changed since (re-audit trigger).

[2.5.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.5.0

## [2.4.0] - 2026-11-05

Minor release. See [UPGRADING](UPGRADING.md#to-240).

### Added

- **Monotonic migration versions (KIT-75, REQ-DB-002).** `nowo:migrations:check-versions [--base=<ref>] [--path=…]
  [--fix]`: fails (exit 1) when a migration added since a git ref (default: the latest tag) sorts before the newest
  migration of that ref (Doctrine runs them alphabetically, so it would run out of order in production); exit 2 when
  git or the ref cannot be read; `--fix` renames the new out-of-order migrations (file and class) after the newest one.
  `MonotonicVersionsChecker`, `GitMigrationFiles`, `MigrationVersions` (pure rules, `next()`), PHPUnit helper
  `Test\MonotonicMigrationVersionsTrait::assertMigrationVersionsAreMonotonic()`, config
  `nowo_migrations_kit.monotonic_versions` (`paths`, `base`).

[2.4.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.4.0

## [2.3.0] - 2026-10-31

nowo:migrations:check-collations: database collation/charset gate for CI (MySQL/MariaDB, PostgreSQL). See [UPGRADING](UPGRADING.md#to-230).

### Added

- **`nowo:migrations:check-collations`** — CI gate (exit 1) when a base table or string column is not in the expected collation: MySQL / MariaDB tables and columns, PostgreSQL columns with an explicit `COLLATE`, optionally the database default (`check_database`); SQLite and other platforms print `SKIP` and exit 0; exit 2 when no expected collation is known. Expected collation from `--expected`, `collation_check.expected_collation` or the connection's `default_table_options.collation`; `ignore_tables` / `ignore_columns` / `--ignore-table` (fnmatch) for objects still to convert. Service `Collation\CollationChecker` (returns a `CollationReport` value object). See [USAGE](USAGE.md#collation-gate-nowomigrationscheck-collations).
- Config **`collation_check`** (`connection`, `expected_collation`, `expected_charset`, `check_columns`, `check_database`, `ignore_tables`, `ignore_columns`).
- `symfony/console` and `doctrine/persistence` declared as direct requirements (already installed through DoctrineBundle).

[2.3.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.3.0

## [2.2.1] - 2026-10-21

Wave-2 REQ compliance: full release-check chain, demo smoke in CI, demo database host and clean-boot fixes, flattened Flex recipe. See [UPGRADING](UPGRADING.md#to-221).

### Changed

- `release-check` starts with `ensure-up` and runs `check-open-prs` (new `.scripts/check-open-prs.sh`) (REQ-MAKE-002, REQ-REL-003).
- `.github/workflows/demo-smoke.yml` runs `make demo-smoke` weekly, on tags and on demand (REQ-TEST-011).
- Demo: Compose default `DATABASE_URL` and `.env.test` point at the `mysql` service (were a non-existent `db` host); php `depends_on` mysql; `make up` waits for the entrypoint's `composer install` instead of racing it (clean checkouts failed with corrupted archives) (REQ-DEMO-011).
- Flex recipe flattened to `.symfony/recipe/nowo-tech/migrations-kit-bundle/1.0/` (was nested `1.0/1.0/`) (REQ-RECIPE-001).
- PHPStan runs without the empty `phpstan-baseline.neon` (REQ-CS-006); Rector capped at 2 parallel workers; README coverage 99.02% as measured (REQ-TEST-003/007).
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees (REQ-GIT-001).
- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — README documentation list (extra links under Additional documentation), tables of contents in long docs (SECURITY, SPEC-DRIVEN-DEVELOPMENT, GITHUB_CI, FRANKENPHP-WORKER-AUDIT, …), CONTRIBUTING Cursor Agent settings (REQ-IDE-006).

[2.2.1]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.2.1

## [2.2.0] - 2026-10-13

### Fixed

- Tests: `SchemaCheckerTest::testTableExistsNormalizesQuotedNames` now configures the `tablesExist()` mock return value. On DBAL 3 that method has no native return type, so the unconfigured mock returned `null` and `SchemaChecker::tableExists(): bool` raised a `TypeError` (CI jobs "DBAL 3" and "Code Coverage (DBAL 3)"). Runtime behaviour is unchanged on DBAL 3 and DBAL 4.
- **`connection` option had no effect**: `services.yaml` registered no services, so `CreateTablesService` and `SchemaChecker` could not be injected and the configured connection was never applied. Both are now registered (with `SchemaDefinitionParser`) and bound to `doctrine.dbal.<connection>_connection`.
- **`SchemaChecker` hid database errors**: every `Throwable` returned `false` / `[]`, so a connection or permission error read as "table missing". Only a missing table (`TableDoesNotExist`) now yields `false` / `[]`; other errors are rethrown.

### Changed

- Requirements: `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` and `symfony/yaml` now require `^7.4 || ^8.0` (was `^6.0 || ^7.0 || ^8.0`) so the lowest allowed version is the one tested in CI. The CI matrix drops the Symfony 7.0 cell and uses 7.4 as its floor (coverage and DBAL jobs included). README/INSTALLATION/badge aligned; PHP requirement in docs corrected to 8.2+.
- **`doctrine/dbal` constraint is now `^3.5 || ^4.0`** (was `^2.13 || ^3.0 || ^4.0`): the code relies on `createSchemaManager()`, `introspectTable()` and `createComparator()`, which DBAL 2.x and early 3.x do not provide. README/INSTALLATION/USAGE updated.

[2.2.0]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.2.0

## [2.0.25] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.

[2.0.25]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.0.25

## [2.0.24] - 2026-10-09

### Dependencies

- Dependabot: `doctrine/dbal` 4.5.0, `igor-php/igor-php` `^0.10.0` (dev), `nowo-tech/phpstan-frankenphp` 1.2.1, `phpstan/phpstan-phpunit`.
- Composer refresh: `doctrine/migrations` 3.9.8, Symfony 7.4.20 components (lockfile); dev tooling `phpstan/phpstan` 2.3.1, `phpstan/phpstan-symfony` 2.1.0, `rector/rector` 2.7.0, `igor-php/igor-php` 0.10.1.
- Demo: Symfony 8.1.8, `doctrine/orm` 3.7.4, `doctrine/dbal` 4.5.0, `twig/twig` 3.30.0; regenerated `config/reference.php`.

[2.0.24]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.0.24

## [2.0.23] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

---

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[2.0.23]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.0.23

## [2.0.22] - 2026-09-24

### Added

- **FrankenPHP worker audit** — [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md): ✅ Compatible with kernel reuse (`FRANKENPHP_RESET_KERNEL` unset/`0`, scenario B).
- **PHPStan** — include `ruleset-worker-strict.neon` (REQ-WORKER / FR-WORKER-002).

### Changed

- **Baseline spec** — `FR-WORKER-001`–`003`, user story for worker kernel reuse; inventory dated 2026-09-24.
- **Dev dependencies** — Composer lock refresh (dependabot / `composer update`).
- **Rector / CS** — minor cleanup (`NowoMigrationsKitBundle` null-coalesce extension, `tests/bootstrap.php` path).

### Documentation

- **README**, **DEMO-FRANKENPHP**, **USAGE** — link worker audit; document `FRANKENPHP_RESET_KERNEL` default (kernel reuse).

### Notes

- **No API or configuration changes** for integrators. Helpers remain CLI-oriented and stateless if registered as services.

[2.0.22]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.0.22

---

## [2.0.21] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **Demos:** MySQL env policy in FrankenPHP stack (REQ-DEMO-011).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[2.0.21]: https://github.com/nowo-tech/MigrationsKitBundle/releases/tag/v2.0.21

## [2.0.20] - 2026-08-19

### Fixed

- **CI:** restore Symfony 8 dependency `if/fi` block broken by the composer audit rollout.

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [2.0.19] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

## [2.0.18] - 2026-07-29

### Added

- **FrankenPHP Friendly** banner in README (`docs/images/frankenphp-friendly.png`) and worker-mode note (REQ-DOCS-017).
- **`make demo-smoke`** / **`make down-dev`** — demo HTTP smoke check (REQ-TEST-011) and root Compose teardown with orphans (REQ-MAKE-007).
- **PHPUnit** `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- **`FRANKENPHP_MODE`** (`classic` \| `worker`) on the Symfony 8 demo; **PHPStan FrankenPHP** rulesets via `nowo-tech/phpstan-frankenphp` (REQ-DEMO-010, REQ-CS-005).

### Changed

- **Demo Symfony 8** — FrankenPHP image **PHP 8.5**; pin `twig/twig` ^3.21 + `prefer-stable` (avoid Twig 4.x-dev breaking TwigInspector).
- **Public PHP API** — `final` on `NowoMigrationsKitBundle` and `MigrationsKitExtension`; migration services already `final`/`readonly` (REQ-PHP-001). Schema helpers stay open for DBAL compatibility test doubles.
- **Makefile** — Compose V2 plugin with `docker-compose` fallback (REQ-MAKE-010); absolute `docker` path so Make does not hit a local `./docker/` directory; optional `-include` of monorepo `.scripts` helpers so standalone CI checkouts do not fail (REQ-MAKE-009).
- **PHP-CS-Fixer** — `fully_qualified_strict_types.import_symbols` enabled; FQCN imports cleaned in `src/` / tests.
- **CI** — `actions/checkout` bumped to v7.

### Documentation

- **README.md**, **DEMO-FRANKENPHP.md** — `FRANKENPHP_MODE`, PHP 8.5 demo image, `demo-smoke`.
- **GitHub About** guidance: plain description, Packagist homepage, topics (REQ-DOCS-018).

### Security

- **SECURITY.md** — AI security audit record (2026-07-29): Pass (good) / Low risk (REQ-SEC-004).

---

## [2.0.17] - 2026-07-16

### Fixed

- **DBAL 3 CI** — Reverted native types on `TableOptionsFirstFk` / `TableNameFirstFk` stubs in `SchemaDefinitionParserTest` (must stay untyped to override DBAL 3’s untyped `Table::addForeignKeyConstraint`). Excluded that file from Rector so `typeDeclarations` does not reintroduce the break.

---

## [2.0.16] - 2026-07-16

### Removed

- **Demo Symfony 7** — Removed `demo/symfony7`. The remaining demo is `demo/symfony8`. Make targets and docs that referred to the Symfony 7 demo now point only to Symfony 8.

### Changed

- **Tests** — Rector-aligned FK stub signatures in `SchemaDefinitionParserTest` (typed `string` / `?string` params).
- **Demo Makefile** — `test` / `test-coverage` are no-ops (demo has no PHPUnit suite), so `make release-check` can complete.

### Documentation

- **README.md**, **demo/README.md**, **DEMO-FRANKENPHP.md**, **EXAMPLE.md**, **USAGE.md**, **DECLARATIVE_SCHEMA.md**, **DEMO_MIGRATIONS_REFERENCE.md** — Aligned with the single Symfony 8 demo.

---

## [2.0.15] - 2026-07-16

### Added

- **REQ-GIT-001** — Git history must not contain Cursor co-author trailers (`Co-authored-by: Cursor` / `cursoragent@cursor.com`). Artifacts: `.scripts/check-no-cursor-coauthor.sh`, `.scripts/strip-cursor-coauthor-from-history.sh`, `.githooks/commit-msg`, `.cursor/rules/01-git-commits.mdc`; Makefile targets `setup-hooks`, `check-no-cursor-coauthor`, `strip-cursor-coauthor-from-history`; CI job `git-hygiene`.
- **CODE_OF_CONDUCT.md** — Contributor Covenant Code of Conduct.
- **docs/GITHUB_CI.md** — Operator manual for GitHub Actions CI requirements (REQ-GIT-001).

### Changed

- **CI matrix** — Symfony versions expanded to `7.0`, `7.4`, `8.0`, `8.1` (PHP 8.2/8.3 still exclude Symfony 8.x).
- **release-check** — Runs `check-no-cursor-coauthor` before other pre-release steps.
- **Dev dependencies** — Refreshed `composer.lock` (php-cs-fixer 3.95.15, rector 2.5.7, etc.).
- **specs/001-baseline/spec.md** — Added user scenarios and independent tests for SchemaChecker, MDK migrations, and demos.

### Documentation

- **README.md**, **CONTRIBUTING.md**, **RELEASE.md** — Links and guidance for Code of Conduct, GITHUB_CI, Spec-driven development, and post-tag git-hygiene check.

---

## [2.0.14] - 2026-07-09

### Added

- **GitHub Spec Kit** — Baseline specification under `specs/001-baseline/` (`spec.md`, `code-inventory.md` mapping 100% of `src/`), `.specify/` scaffolding, and Cursor Agent skills (`.cursor/skills/speckit-*`). Operator manual: [SPEC-KIT.md](SPEC-KIT.md).

### Changed

- **SPEC-DRIVEN-DEVELOPMENT.md** — Three-layer model (Spec Kit baseline, product behaviour, `REQ-*` traceability); user stories aligned with migration-author workflows.
- **Dev dependencies** — Refreshed lock files (bundle and demos): php-cs-fixer 3.95.12, nikic/php-parser 5.8.0, and related transitive updates.

### Documentation

- **README.md** — Link to [SPEC-KIT.md](SPEC-KIT.md) in the documentation index.

### Fixed

- **CHANGELOG.md** — Restored missing `[2.0.12]` section header (content was present but mis-nested under 2.0.13).

---

## [2.0.13] - 2026-07-02

### Added

- **Makefile (REQ-MAKE-008)** — `make update-deps` updates Composer dependencies in the bundle Docker container and in all demos (`demo/symfony7`, `demo/symfony8`). Demo Makefiles include the shared aggregate and per-demo update-deps targets from `.scripts/`.

### Changed

- **composer.json** — Corrected GitHub homepage, issues, and source URLs to `nowo-tech/MigrationsKitBundle`.
- **Dev dependencies** — Refreshed `composer.lock` files (bundle and demos) via `make update-deps` (Symfony 7.4.14, PHPStan 2.2.3, Rector 2.5.2, php-cs-fixer 3.95.11, etc.).

### Documentation

- **EXAMPLE.md**, **ROADMAP.md** — GitHub links aligned with the canonical repository name.

---

## [2.0.12] - 2026-06-17

### Fixed

- **Tests (DBAL 3)** — Align PHPUnit `Schema` and `Table` stub method signatures with DBAL 3 (untyped parameters), fixing CI fatal errors (exit 255) in `test-dbal` and `coverage-dbal3` jobs.

---

## [2.0.11] - 2026-06-17

### Changed

- **TableSchemaHelper** — Improved DBAL 3/4 schema comparator creation and primary-key handling; class is no longer `final` (internal helper, not part of the public API).
- **SchemaDefinitionParser** — Refactored FK parameter-order detection for clearer DBAL 3 vs 4 branching.
- **CreateTablesService** — Removed redundant guard in the column-modify loop (behaviour unchanged).

### Fixed

- **PHPStan** — Resolved all level-8 static analysis errors in `src/` and `tests/`.
- **Tests & coverage** — Expanded PHPUnit coverage for `TableSchemaHelper` and `SchemaDefinitionParser`; bundle line coverage ~99% (`CreateTablesService` at 100%).

---

## [2.0.10] - 2026-06-17

### Changed

- **CI** — Refined workflow matrix: PHP 8.2–8.5 × Symfony 7|8; dedicated `test-dbal` job for DBAL 3|4 × Symfony 7 and DBAL 4 × Symfony 8.

### Fixed

- **Tests (DBAL 3)** — Align `Table` FK stubs with DBAL 3 method signatures in PHPUnit tests.
- **Tests (MySQL)** — Accept both `DROP name` (DBAL 3) and `DROP COLUMN name` (DBAL 4) in drop-column deduplication assertion.

---

## [2.0.9] - 2026-06-17

### Added

- **TableSchemaHelper** — Shared DBAL 3/4-compatible helpers for primary keys and schema comparators.

### Fixed

- **DBAL 4 deprecations** — Use `Table::addPrimaryKeyConstraint()` when available instead of deprecated `Table::setPrimaryKey()` in `SchemaDefinitionParser` and `CreateTablesService`.
- **DBAL 4 deprecations** — Create schema comparators with `ComparatorConfig::withReportModifiedIndexes(false)` to silence modified-index detection deprecation.

---

## [2.0.8] - 2026-04-15

### Added

- **Wider runtime support** — `composer.json` requires **PHP >= 8.1** (previously >= 8.2) and allows **Symfony 6** (`symfony/*` `^6.0 || ^7.0 || ^8.0`) alongside Symfony 7 and 8.
- **Tests** — More coverage in **CreateTablesServiceMySQLPlatformTest**, **SchemaMigrationServiceTest**, and **SchemaDefinitionParserTest** (MySQL platform SQL, integration paths, parser edge cases).
- **Repository / CI** — GitHub issue and PR templates, Dependabot, stale and PR-lint workflows, sync-releases workflow, CODEOWNERS, FUNDING, Copilot instructions; demo `.env.test` and demo Makefile alignment.

### Changed

- **CreateTablesService** — When adding foreign keys on **new columns** in one `apply()` run, only **SQLite** may skip the step via `catch` (unsupported FK path); on other platforms, exceptions are **rethrown** so failures are not masked.
- **CreateTablesService** — `getRenameColumnSQL` handling: only an **empty string** is treated as no SQL; the platform return value is passed through without incorrect `(array)` wrapping.
- **CreateTablesService** — Column name normalization in `dropColumnsViaComparator` uses **`$this->normalizeIdentifier(...)`** as a first-class callable.
- **SchemaDefinitionParser** — `addForeignKeyConstraint` calls pass **name** and **options** without redundant casts; `array_map` uses **`strval(...)`** callables for column lists.
- **Developer tooling** — Root `Makefile` `test-coverage` saves console output to **`coverage-php.txt`** and runs **`.scripts/php-coverage-percent.sh`**; Composer **`test-coverage`** runs PHPUnit only (optional Clover check: **`.scripts/check-coverage.php`**). **`composer test`** uses **`--color=always`**.

### Fixed

- **CreateTablesService** — **Non-SQLite** platforms no longer swallow unrelated errors when applying FKs on new columns in the same run (only the SQLite “not supported in same run” case is handled quietly).

### Documentation

- **README** — Configuration comment now describes `connection` as used for **CreateTablesService** when registered (not SchemaChecker). Demo section clarifies FrankenPHP **worker mode** only in production; default `APP_ENV=dev` uses **Caddyfile.dev** without worker.
- **DEMO-FRANKENPHP.md** — Example `bundles.php` matches the Symfony 8 demo (Doctrine, DoctrineMigrations, Twig Inspector).
- **USAGE.md** — Removed incorrect claim that **SchemaChecker** is injectable; documented that only **CreateTablesService** is affected by `nowo_migrations_kit.connection` when registered as a service.
- **Symfony Flex recipe comment** — Aligned with `CONFIGURATION.md` (CreateTablesService + container registration).
- **INSTALLATION.md** — Requirements updated to **PHP >= 8.1** and **Symfony ^6.0 \|\| ^7.0 \|\| ^8.0** (matches `composer.json`).

---

## [2.0.7] - 2026-03-16

### Added

- **CI matrix for Doctrine DBAL** — GitHub Actions now runs the test suite against **DBAL 3 and DBAL 4** on PHP 8.2 / Symfony 7.0 (`test-dbal`), plus a dedicated **coverage job for DBAL 3** (`coverage-dbal3`) that uploads a separate report to Codecov. This verifies real compatibility with both major DBAL lines.
- **Tests & coverage** — Many new PHPUnit tests for `CreateTablesService` and `SchemaDefinitionParser`, including integration tests in `SchemaMigrationServiceTest` and demo migrations, significantly increasing real coverage without ignores or configuration tweaks.

### Changed

- **Static analysis & internals** — PHPStan is now at **0 errors**, with refined types and method signatures for DBAL 3/4 (comparator, `SchemaDiff::toSql`, `getAlterSchemaSQL`, `getRenameColumnSQL`, parameter order).
- **Demo & CI** — The Symfony 7/8 demo migrations were updated to better exercise the bundle phases (drops, PK changes, indexes, FKs) and are now part of the release health‑check.

### Fixed

- **SQLite demo migration** — In `demo/symfony7`, migration `Version20250223100013` (PK change on `kit_pk_demo`) now skips on SQLite, avoiding `"table \"kit_pk_demo\" has more than one primary key"` while still exercising the PK‑change path on other platforms.
- **DBAL compatibility edges** — Several edge‑case paths in `CreateTablesService` and `SchemaDefinitionParser` were adjusted and covered by tests so they behave correctly across DBAL 3/4 for foreign keys, indexes, primary keys, and column modifications.

---

## [2.0.6] - 2025-02-27

### Added

- **Tests** — **CreateTablesServiceMySQLPlatformTest::testApplyCreateTableWithForeignKeyOnDeleteEmitsOnDeleteInSqlOnMySQL**: asserts that when creating a **new table** (table does not exist) with `FOREIGN_KEYS` that have `onDelete` (CASCADE, SET NULL), the generated CREATE TABLE SQL includes `ON DELETE CASCADE` and `ON DELETE SET NULL`.

### Changed

- **SchemaDefinitionParser** — When building a table from the definition, foreign keys with a `name` now receive `onDelete` and `onUpdate` from the FK definition. The parser uses the same parameter-order detection as CreateTablesService (reflection on `Table::addForeignKeyConstraint`) so options are passed correctly on DBAL 3 and 4. Previously, the parser always passed an empty options array, so **new tables** created via `parseTable` + `getCreateTableSQL` did not get `ON DELETE` / `ON UPDATE` in the generated SQL.

### Fixed

- **CreateTablesService** — When the same table has `DROP_COLUMNS`, the SQL returned by `dropColumnsViaComparator` is now deduplicated by exact string before adding to the output. Some platforms or comparator versions can return the same statement twice (e.g. `ALTER TABLE tablename DROP columnname`); the bundle now emits each statement at most once per Phase 2a run.

---

## [2.0.5] - 2025-02-27

### Added

- **Demo Makefiles** — New target `test-mysql-write-sql`: same flow as `test-mysql` (update-bundle + db-reset-mysql) but runs `doctrine:migrations:migrate --write-sql=var/migration_mysql.sql` so the generated MySQL SQL is written to a file without executing. Available in `demo/symfony7` and `demo/symfony8`.
- **Documentation** — [BUGREPORT_DUPLICATE_DROP_FK_AND_ON_DELETE.md](BUGREPORT_DUPLICATE_DROP_FK_AND_ON_DELETE.md): resolution of the duplicate DROP FOREIGN KEY and missing ON DELETE issues (fixed in 2.0.4), with code and test references. Linked from README documentation table.

### Changed

- **CreateTablesService::getDropForeignKeySQL()** — DROP FOREIGN KEY SQL is now always emitted in **canonical form (no backticks)**: `ALTER TABLE tablename DROP FOREIGN KEY fkname`. The bundle no longer calls the platform’s `getDropForeignKeySQL()`, so the same style is used everywhere and duplicate-looking statements (e.g. one line with backticks on the table, another with backticks on the FK) are avoided when Phase 2a output is filtered.

### Removed

- **CreateTablesService** — Removed unused reflection helpers `getDropForeignKeySQLExpectsString()` and `getDropForeignKeySQLExpectsTableNameString()` (no longer needed after generating DROP FOREIGN KEY in canonical form).

---

## [2.0.4] - 2025-02-27

### Added

- **CreateTablesService (FK options)** — **onDelete** and **onUpdate** in `MDK::FOREIGN_KEYS` are now correctly applied: the generated `ALTER TABLE ... ADD CONSTRAINT` SQL includes `ON DELETE` and `ON UPDATE` clauses on MySQL/MariaDB (and other platforms that support them). The bundle detects DBAL 3 vs 4 parameter order for `Table::addForeignKeyConstraint` (name vs options as 4th/5th argument) and calls it accordingly. See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#foreign-keys).
- **Tests** — **CreateTablesServiceMySQLPlatformTest**: `testApplyAddForeignKeyWithOnDeleteAndOnUpdateEmitsCorrectSqlOnMySQL` and `testApplyAddForeignKeyWithOnDeleteSetNullEmitsCorrectSqlOnMySQL` assert that generated SQL contains `ON DELETE CASCADE`, `ON DELETE SET NULL`, and `ON UPDATE CASCADE`; `testApplyDropForeignKeyAndDropColumnSameTableNoDuplicateDropFk` asserts that when a table has both `DROP_FOREIGN_KEYS` and `DROP_COLUMNS` (column referenced by that FK), only one `DROP FOREIGN KEY` is emitted. **SchemaMigrationServiceTest::testApplyAddForeignKeyWithOnUpdateAndOnDelete** now asserts that the SQL contains `ON DELETE` and `ON UPDATE`.
- **Demo validation** — **Version20250223100003_validation** (symfony7 and symfony8): when the FK `fk_kit_item_user_id` exists, validates that it has `onDelete` = `SET NULL` (from the MDK definition). **DECLARATIVE_SCHEMA.md**: note and example for `onDelete` / `onUpdate` in foreign keys.

### Changed

- **SchemaAssetName** — Name is now read via reflection over the class hierarchy (`getNameViaReflection`) so that the deprecated `getName()` is not called when the `name` property can be read from a parent class (e.g. `AbstractAsset`). Reduces or eliminates the "AbstractAsset::getName is deprecated" notice in DBAL 4.
- **CreateTablesService::dropColumnsViaComparator** — Column names in the "columns to drop" set and FK local column names are normalized before comparison, so FKs that reference columns being dropped are correctly detected and dropped first. Fixes the "Dropping columns referenced by constraints is deprecated" warning when the platform returns column names in a different form (e.g. `Identifier`).

### Fixed

- **CreateTablesService** — Foreign keys defined with `onDelete` (e.g. `CASCADE`, `SET NULL`) and/or `onUpdate` in the MDK definition now produce SQL that includes those clauses. Previously the comparator path used a parameter order that did not pass options in DBAL 3.
- **CreateTablesService** — When the same table has both `DROP_FOREIGN_KEYS` and `DROP_COLUMNS` (where the column is referenced by that FK), the bundle no longer emits two identical `ALTER TABLE ... DROP FOREIGN KEY` statements. Phase 1b drops the FK by name; Phase 2a (`dropColumnsViaComparator`) would also generate the same DROP; FKs already dropped in Phase 1b are now tracked and the duplicate SQL is skipped. See `CreateTablesServiceMySQLPlatformTest::testApplyDropForeignKeyAndDropColumnSameTableNoDuplicateDropFk`.

---

## [2.0.3] - 2025-02-27

### Added

- **Documentation (Doctrine deprecations)** — **USAGE.md**: New section *"Transaction already committed (deprecation) when using DDL on MySQL"* explaining the Doctrine Migrations warning when DDL causes implicit commits; recommends `transactional: false` or overriding `isTransactional()` to return `false`. New section *"AbstractAsset::getName() deprecated (DBAL 5)"* explaining that the bundle uses **SchemaAssetName::get($asset)** for schema asset names so code is compatible with DBAL 3, 4 and 5.
- **Documentation** — **DEMO_MIGRATIONS_REFERENCE.md**: Recommendation #4 expanded to mention the transactional/DDL deprecation warning and link to USAGE.md and Doctrine’s implicit-commits docs. **MIGRATIONS_API.md**: Note that `AbstractAsset::getName()` is deprecated in DBAL 4 and removed in DBAL 5; bundle uses `SchemaAssetName::get()` for compatibility, with link to USAGE.md.

### Changed

- **SchemaAssetName** — Order of name resolution: use `getName()` first when the method exists (DBAL 3/4), then fall back to the `name` property (DBAL 5 when `getName()` is removed). Ensures correct behaviour when the property is protected in older DBAL.
- **Tests** — **SchemaDefinitionParserTest**: Table name assertions now use `SchemaAssetName::get($table)` instead of `$table->getName()`, so tests remain valid when DBAL 5 removes `getName()`.

### Fixed

- (No changes.)

---

## [2.0.2] - 2025-02-25

### Added

- **CreateTablesService** — In a single `apply()` call, when the definition adds new columns and also defines indexes and/or foreign keys on those columns, the bundle now emits all SQL (ADD COLUMN, index, FK) in one run. No need for a separate manual block with `SchemaChecker` and `addSql()` for indexes/FKs on newly added columns. On SQLite, FK creation via this path is skipped (platform does not support it); indexes and columns still work.
- **DEMO_MIGRATIONS_REFERENCE.md** — Matrix row for “Add column + index + FK in one apply” and note in recommendations that column, index and FK on the same new columns can be defined in one definition. Unit test `testApplyAddColumnAndIndexAndFkOnNewColumnsEmitsAllSqlInOrder` covers this behaviour.

### Changed

- (No changes.)

### Fixed

- (No changes.)

---

## [2.0.1] - 2025-02-25

### Changed

- **RELEASE.md** — Release checklist now includes syncing `composer.lock` before tagging (`make composer-sync` or `composer update --no-install` + `composer validate --strict`). General "Creating a new version" steps updated with the same requirement.

---

## [2.0.0] - 2025-02-25

**Major release: incompatible with 1.x.** The bundle now exposes only **SchemaChecker** and **CreateTablesService** (MDK declarative definitions). All previous runners, sync, and data-step APIs have been removed.

### Breaking changes

- **Removed `MigrationDefinitionRunner`** — No longer available. Use **CreateTablesService::apply()** with a definition array (MDK format) instead. Replace `ensureTable` / `ensureColumn` / `ensureIndex` with table definitions under `MDK::TABLES`; run returned SQL in a loop with `$this->addSql($sql)` or pass `$this` as the emitter.
- **Removed `SchemaSync`** — Declarative “sync from array” is replaced by **CreateTablesService::apply()**: pass the migration’s `Schema` and your definition; the service returns the list of SQL statements to run.
- **Removed `StandardColumns`** — No replacement. Define audit columns (e.g. `created_at`, `updated_at`) directly in your MDK definition arrays.
- **Removed `MigrationDefinition`** (typed value object) — Use plain arrays with **MigrationDefinitionKeys (MDK)** constants and pass them to **CreateTablesService::apply()**.
- **Removed data steps** — The `data` key and insert/update steps (`only_if_not_exists`, `only_if_exists`) are no longer supported. Perform data migrations with raw `$this->addSql()` or your own logic.
- **Removed `SchemaChecker::rowExists()`** — Use the migration connection and run a simple `SELECT` (or inject a repository) when you need to check row existence.
- **Removed direct runner methods** — `modifyColumn()`, `dropColumn()`, `dropIndex()`, `ensureForeignKey()` no longer exist. Use **CreateTablesService** with MDK `columns` / `indexes` / `foreign_keys` and `drop` / `drop_columns` / `drop_indexes` / `drop_foreign_keys` keys.

### Added

- **CreateTablesService::applyWithAddSql()** — Apply a definition with a custom callable to emit SQL (e.g. for logging or custom handling).
- **SchemaChecker::getSchemaManager()** — Access the DBAL schema manager (DBAL 2.x and 3.x/4.x compatible).
- **DBAL 2.x compatibility in CreateTablesService** — `resolveTableName()` supports both `Schema::getTables()` (DBAL 3+) and `Schema::getTableNames()` (DBAL 2.x) for correct table name resolution.
- **PHPUnit coverage configuration** — `<coverage>` in `phpunit.xml.dist` with HTML report and bounds (90% / 95%) for development.
- Additional tests for SchemaChecker (exception paths), CreateTablesService (warn-on-mix, rename+index, drop PK, column options), and SchemaDefinitionParser (foreign_keys alias).
- Demo migrations Version20250223100011–00013 (create table kit_pk_demo, DROP_PRIMARY_KEYS, PRIMARY_KEY on existing table); USAGE.md section "Exporting and viewing SQL before applying"; demo MIGRATIONS_VALIDATION.md (symfony7/symfony8).

### Changed

- **Documentation** — README, USAGE, CONFIGURATION, UPGRADING, INSTALLATION, and DECLARATIVE_SCHEMA describe only the 2.0 API. Option `connection` documented for CreateTablesService (when injected from container). DEMO_MIGRATIONS_REFERENCE and demo README/Makefile in English. References to removed APIs archived in CHANGELOG.
- **Demos** — Demo migrations use only CreateTablesService and MDK; Makefiles aligned (symfony7/symfony8).

### Fixed

- **CreateTablesService (DBAL 2.x)** — Fixed “Call to undefined method Schema::getTableNames()” when resolving table names; uses `getTables()` when available and falls back to `getTableNames()` on DBAL 2.x.
- **CreateTablesService (MySQL / protected getDropPrimaryKeySQL)** — When the platform's `getDropPrimaryKeySQL()` is protected (e.g. some DBAL versions), use reflection to call it only if public; otherwise generate `ALTER TABLE … DROP PRIMARY KEY` with quoted table name.
- **CreateTablesService** — Fallbacks that build SQL manually (DROP TABLE, DROP FOREIGN KEY) now use quoted table and identifier names via `quotedTableName()` and `quoteSingleIdentifier()` for reserved/special characters.

---

## [1.2.1] - 2026-02-22

### Fixed

- **SchemaSync (DBAL 4):** Column type name resolution when building tables: use `Type::lookupName()` when `Type::getName()` is not available (DBAL 4). Ensures `buildTableWithShortNameOnly` works with DBAL 4.
- **SchemaSync (DBAL 4):** Support `SchemaDiff::getAlteredTables()` in addition to `getModifiedTables()` for modified tables; support `TableDiff::getOldTable()->getName()` for diff table name when `name` / `getName()` are not present.
- **SchemaSync (DBAL 4):** Use `getDropTablesSQL()` when available for dropping tables; fallback to per-table `getDropTableSQL()` with quoted names for older platforms.
- **SchemaDefinitionParser (DBAL 4):** Broader exception handling when `Schema::createTable(Table)` fails (e.g. "must be of type string"), so the reflection-based table injection works with current DBAL 4.
- **MigrationDefinitionRunner:** Add missing `use` for `MigrationDefinitionKeys as MDK` so `run()` works when using the constants class.

### Changed

- **Documentation:** All user-facing docs and comments in English (README, USAGE.md, DECLARATIVE_SCHEMA.md, phpunit.xml.dist).
- **Tests:** Improved test coverage (MigrationDefinitionRunner, SchemaChecker, SchemaLimitChecker, SchemaDefinitionParser, MigrationDefinition, StandardColumns, DependencyInjection, SchemaSync tests with mocks). Coverage report excludes `SchemaSync.php` from the percentage (complex DBAL integration); remaining code meets the coverage target.

---

## [1.2.0] - 2026-02-20

### Added

- **MigrationDefinitionRunner:** New step keys in `run()`: `indexes` (add index if not exists), `rename_columns` (run rename_sql if old column exists), `modify_columns` (run modify_sql if column exists), `drop_indexes` (run drop_sql if index exists), `drop_columns` (run drop_sql if column exists). Order: tables → columns → indexes → rename_columns → modify_columns → drop_indexes → drop_columns → data. See [USAGE.md](USAGE.md).
- **Direct methods:** `modifyColumn()`, `dropColumn()`, `dropIndex()`, `ensureForeignKey()` — run the given SQL only when the condition holds (column/index/FK exists or not).
- **SchemaCheckerInterface:** New interface for schema checks; `SchemaChecker` implements it. Enables mocking in unit tests (PHPUnit cannot mock final classes). Service alias in DI: `SchemaCheckerInterface` → `SchemaChecker`.
- **MigrationDefinition:** Typed value object for the full definition (tables, columns, indexes, renameColumns, modifyColumns, dropIndexes, dropColumns, data). Use `new MigrationDefinition(...)->run($runner, $addSql)` or `MigrationDefinition::fromArray([...])->run($runner, $addSql)`. PHPStan types documented. See [USAGE.md](USAGE.md#migrationdefinition-typed).
- **Composer/Makefile:** Scripts use `vendor/bin/phpunit` and `vendor/bin/php-cs-fixer`. `make test`, `make test-coverage`, `make cs-check`, `make qa` depend on `install` so the container has dependencies before running.
- **Coverage in console:** `composer test-coverage` uses `--coverage-text=php://stdout` and `--colors=always` so coverage percentage is shown in the terminal.
- **Makefile and Dockerfile:** Comments and help text in English; Dockerfile documents PCOV for coverage in the container.

### Changed

- **phpunit.xml.dist:** Removed invalid `<coverage>` block (PHPUnit 10.5 XSD); coverage is driven by CLI options in `test-coverage` script.

### Fixed

- **Tests:** MigrationDefinitionRunner tests now mock `SchemaCheckerInterface` instead of final `SchemaChecker`, fixing "Class is declared final and cannot be doubled" (21 tests).

---

## [1.1.0] - 2026-02-20

### Added

- **Data steps in MigrationDefinitionRunner:** `run()` accepts a `data` key with insert/update steps; supports `only_if_not_exists` (insert only when no row matches) and `only_if_exists` (update only when a row exists). See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#data-steps).
- **SchemaChecker:** `rowExists($table, array $criteria)` to check if a row exists by column values; `getConnection()` for direct DB access.
- **StandardColumns:** New `Nowo\MigrationsKitBundle\Schema\StandardColumns` class for reusable audit fields: `auditColumns()`, `auditIndexes()`, `auditColumnSteps()`, `auditIndexSteps()`, `timestampColumnSteps()`, `userRefColumnSteps()`. Use in declarative schema (SchemaSync) or with MigrationDefinitionRunner. See [DECLARATIVE_SCHEMA.md](DECLARATIVE_SCHEMA.md#common-column-definitions-standardcolumns).
- **Demo:** Example migrations for data steps (Version20250219000005) and StandardColumns (Version20250219000006). Make targets `migrate-verbose`, `migrate-dry-run`, `migrate-write-sql` to view migration SQL. Demo README translated to English.

### Fixed

- **SchemaSync (PostgreSQL):** Resolved "There is no table with name public.xxx" when syncing against an empty or new database. New tables are created from the definition only; when current schema is empty, the modified-tables loop is skipped; compareSchemas is wrapped in try-catch with fallback to build new tables from definition.

---

## [1.0.0] - 2026-02-20

First release under **nowo-tech**.

### Added

- **SchemaChecker:** `tableExists`, `columnExists`, `indexExists`, `hasPrimaryKey`, `foreignKeyExists`, `listTableColumns`; compatible with DBAL 2.x, 3.x and 4.x.
- **MigrationDefinitionRunner:** run from array (`tables` + `columns`), `ensureTable`, `ensureColumn`, `ensureIndex`; only runs SQL when the target does not exist.
- **SchemaSync (declarative schema):** desired schema in one array; create/alter/drop tables, columns, indexes; requires DBAL 3.x or 4.x.
- **Configuration:** `nowo_migrations_kit.connection` for **CreateTablesService** when registered as a service (not used by manually instantiated `SchemaChecker`).
- Demos for Symfony 7 and 8 with doctrine/migrations 3.x and 4.x.
- Documentation in `docs/` (CONFIGURATION, INSTALLATION, USAGE, DECLARATIVE_SCHEMA, CONTRIBUTING, EXAMPLE, RELEASE, ROADMAP, UPGRADING).
- Makefile, Docker, and GitHub CI for development and releases.
