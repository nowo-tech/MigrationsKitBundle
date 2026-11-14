<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Test\MonotonicMigrationVersionsTrait;
use RuntimeException;
use Throwable;

use function array_column;
use function array_unique;
use function basename;
use function bin2hex;
use function chgrp;
use function chmod;
use function count;
use function dirname;
use function fclose;
use function fflush;
use function file_exists;
use function file_get_contents;
use function filegroup;
use function fileperms;
use function flock;
use function fopen;
use function fsync;
use function fwrite;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function link;
use function random_bytes;
use function realpath;
use function rename;
use function sprintf;
use function strcasecmp;
use function stream_get_meta_data;
use function strlen;
use function tempnam;
use function token_get_all;
use function unlink;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;
use const T_CASE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_END_HEREDOC;
use const T_FUNCTION;
use const T_GOTO;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NAMESPACE;
use const T_NEW;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_START_HEREDOC;
use const T_STRING;
use const T_USE;
use const T_WHITESPACE;

/**
 * Monotonic migration versions (2.4, KIT-75): every migration added since a base ref (default: the latest tag) must
 * sort after the greatest migration present at that ref, per migrations directory. Used by
 * `nowo:migrations:check-versions` and {@see MonotonicMigrationVersionsTrait}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MonotonicVersionsChecker
{
    /**
     * Tokens after which a name is a member, a function, a constant or an enum case, not a class reference.
     */
    private const NOT_A_CLASS_NAME = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_GOTO, T_CASE];

    /**
     * @param list<string> $paths migrations directories, relative to the project or absolute
     */
    public function __construct(
        private GitMigrationFiles $files,
        private array $paths = ['migrations'],
        private ?string $base = null,
    ) {
    }

    /**
     * One report per directory.
     *
     * @param list<string>|null $paths null = the configured directories
     * @param string|null $base null = the configured base, else the latest tag
     *
     * @throws RuntimeException when the base ref cannot be read
     *
     * @return list<MonotonicVersionsReport> empty when there is no base (no tag yet)
     */
    public function check(?array $paths = null, ?string $base = null): array
    {
        $ref = $base ?? $this->base ?? $this->files->latestTag();
        if ($ref === null) {
            return [];
        }
        $reports = [];
        foreach ($paths ?? $this->paths as $path) {
            $before    = $this->files->atRef($ref, $path);
            $now       = $this->files->current($path);
            $reports[] = new MonotonicVersionsReport($path, $ref, MigrationVersions::max($before), MigrationVersions::added($before, $now), MigrationVersions::violations($before, $now));
        }

        return $reports;
    }

    /**
     * Renames each out-of-order migration of a report (file and class name) to the next version after every other one.
     * Only migrations added since the base are touched: run it before they are pushed or applied anywhere.
     *
     * All or nothing, like {@see fixAll()}.
     *
     * @throws FixFailedException when the migrations cannot be renamed; nothing was changed unless
     *                            {@see FixFailedException::$rollbackIncomplete} is true (the message then says
     *                            "ROLLBACK INCOMPLETE")
     *
     * @return array<string, string> old version => new version
     */
    public function fix(MonotonicVersionsReport $report, DateTimeImmutable $now = new DateTimeImmutable()): array
    {
        return $this->fixAll([$report], $now)[0];
    }

    /**
     * Renames the out-of-order migrations of several reports as one all-or-nothing operation (PB-M-L2).
     *
     * 1. Plan: read every migration, rewrite its class name in memory and refuse anything that would not produce a
     *    loadable migration (unreadable source, taken target, no `class <Version>` declaration, a file listed twice).
     * 2. Stage: write every rewritten migration to a temporary file next to its target, with the source's permissions,
     *    and read it back.
     * 3. Commit: move every temporary file to its target, then remove every source.
     *
     * Nothing is touched before every file is staged. A failure while staging removes the temporary files; a failure
     * while committing is undone from what is on disk: every missing source is written back from memory first, then
     * every target that still holds the bytes this run wrote is removed. If that rollback itself fails, the message says "ROLLBACK INCOMPLETE" and lists every file that needs
     * attention; a source that cannot be restored keeps its renamed copy, so no migration is ever lost. Since 2.8 an
     * unexpected error (not a reported failure, e.g. an `Error` thrown by a stream wrapper) is rolled back the same way
     * and reported as a {@see FixFailedException} with that error as its previous exception.
     *
     * @param list<MonotonicVersionsReport> $reports
     *
     * @throws FixFailedException when the migrations cannot be renamed; nothing was changed unless
     *                            {@see FixFailedException::$rollbackIncomplete} is true (the message then says
     *                            "ROLLBACK INCOMPLETE")
     *
     * @return list<array<string, string>> per report, in order: old version => new version
     */
    public function fixAll(array $reports, DateTimeImmutable $now = new DateTimeImmutable()): array
    {
        $plan    = [];
        $renamed = [];
        $locks   = [];
        $started = 0;
        try {
            [$plan, $renamed] = $this->plan($reports, $now);

            foreach ($plan as $k => $step) {
                // Recorded in the plan as soon as the file exists, even if an error escapes right after (PL-I1).
                $temp = $this->createOwnerOnly($step['dir'], basename($step['to']), $plan[$k]['temp']);
                if ($temp === null || !$this->write($temp, $step['rewritten'], $step['perms'], $step['group'])) {
                    throw $this->failure(sprintf('Cannot rename migration "%s" to "%s": the renamed copy could not be written and checked in "%s".', $step['from'], $step['to'], $step['dir']), $plan, 0);
                }
            }

            foreach ($plan as $k => $step) {
                // Counted before the call: an error thrown after the move took effect still lets the rollback check it.
                $started = $k + 1;
                $problem = $this->publish($step, $locks);
                if ($problem !== null) {
                    throw $this->failure(sprintf('Cannot rename migration "%s" to "%s": %s.', $step['from'], $step['to'], $problem), $plan, $k);
                }
            }
            $this->syncDirectories($plan);

            foreach ($plan as $step) {
                // A source edited since it was read is not removed: its new bytes would be lost.
                if (@file_get_contents($step['from']) !== $step['code'] || !@unlink($step['from'])) {
                    throw $this->failure(sprintf('Cannot remove migration "%s" after writing it as "%s" (it could not be removed, or it changed since --fix read it).', $step['from'], $step['to']), $plan, count($plan));
                }
            }
            $this->syncDirectories($plan);
        } catch (FixFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Anything else (PF-I6: an Error, a Random\RandomException) is rolled back like a reported failure. The
            // rollback reads the disk, so an operation that took effect before it threw is undone too (PL-L1).
            throw $this->failure(sprintf('Cannot rename the migrations: unexpected %s: %s.', $e::class, $e->getMessage()), $plan, $started, $e);
        } finally {
            foreach ($locks as $lockFile => $lock) {
                try {
                    @unlink($lockFile);
                    flock($lock, LOCK_UN);
                    fclose($lock);
                } catch (Throwable) {
                    // Best effort (PL-I2): a lock file left behind does not block the next run, and the renames are done.
                }
            }
        }

        return $renamed;
    }

    /**
     * Plans every rename in memory; nothing on disk is touched.
     *
     * @param list<MonotonicVersionsReport> $reports
     *
     * @throws FixFailedException when a migration cannot be renamed
     *
     * @return array{0: list<array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null}>, 1: list<array<string, string>>}
     */
    private function plan(array $reports, DateTimeImmutable $now): array
    {
        $plan    = [];
        $renamed = [];
        $seen    = [];
        foreach ($reports as $report) {
            $map = [];
            $dir = $this->files->absolute($report->path);
            // The same directory spelled differently (`migrations`, `./migrations`, a symlink) is one key (PF-I2).
            $key      = (@realpath($dir) ?: $dir) . '/';
            $existing = $this->files->current($report->path);
            if ($report->baseMax !== null) {
                $existing[] = $report->baseMax;
            }
            foreach ($report->violations as $old) {
                $new        = MigrationVersions::next($existing, $now);
                $existing[] = $new;
                $from       = $dir . '/' . $old . '.php';
                $to         = $dir . '/' . $new . '.php';
                if (isset($seen[$key . $old]) || isset($seen[$key . $new])) {
                    throw new FixFailedException(sprintf('Cannot rename migration "%s": it is listed more than once (the same directory given twice?). Nothing was changed.', $from));
                }
                $seen[$key . $old] = $seen[$key . $new] = true;
                $code              = is_file($from) ? @file_get_contents($from) : false;
                $perms             = $code === false ? false : @fileperms($from);
                if ($code === false || $perms === false || file_exists($to)) {
                    throw new FixFailedException(sprintf('Cannot rename migration "%s" to "%s". Nothing was changed.', $from, $to));
                }
                $rewritten = $this->renameClass($code, $old, $new);
                if ($rewritten === null) {
                    throw new FixFailedException(sprintf('Cannot rename migration "%s" to "%s": it does not declare "class %s", so its file and class names would no longer match. Nothing was changed.', $from, $to, $old));
                }
                $plan[]    = ['dir' => $dir, 'from' => $from, 'to' => $to, 'code' => $code, 'rewritten' => $rewritten, 'perms' => $perms & 0o7777, 'group' => @filegroup($from), 'temp' => null];
                $map[$old] = $new;
            }
            $renamed[] = $map;
        }

        return [$plan, $renamed];
    }

    /**
     * Moves a staged copy to its target without ever replacing a file that is already there (PF-L2).
     *
     * link() fails when the target exists, so it publishes atomically and never overwrites. Where hard links are not
     * available (some mounts, stream wrappers), the fallback holds an exclusive lock file in the directory for the
     * rest of the run (one per directory, taken on first use) and checks the target before rename(): that excludes a
     * concurrent --fix, not an editor writing the same name in the same instant.
     *
     * @param array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null} $step
     * @param array<string, resource> $locks lock file => handle, kept until the end of the run
     *
     * @return string|null why it failed; null when the target is in place
     */
    private function publish(array $step, array &$locks): ?string
    {
        $temp = (string) $step['temp'];
        if (@link($temp, $step['to'])) {
            @unlink($temp);

            return null;
        }
        if (file_exists($step['to'])) {
            return 'the target already exists (created after --fix planned the rename); it was not replaced';
        }
        $lockFile = $step['dir'] . '/.nowo-migrations-fix.lock';
        if (!isset($locks[$lockFile])) {
            $lock = @fopen($lockFile, 'c');
            if ($lock === false || !@flock($lock, LOCK_EX | LOCK_NB)) {
                return 'the directory could not be locked (another --fix running?)';
            }
            $locks[$lockFile] = $lock;
        }

        return file_exists($step['to']) || !@rename($temp, $step['to']) ? 'the staged copy could not be moved into place' : null;
    }

    /**
     * Best effort: flushes the directory entries (new names, removed names) to disk where the platform allows it.
     *
     * @param list<array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null}> $plan
     */
    private function syncDirectories(array $plan): void
    {
        foreach (array_unique(array_column($plan, 'dir')) as $dir) {
            $handle = @fopen($dir, 'r');
            if ($handle !== false) {
                @fsync($handle);
                fclose($handle);
            }
        }
    }

    /**
     * Renames the class declared as `class <old>` to `<new>`, by tokens: a mention of the name in a comment, a
     * docblock or a string is neither renamed nor taken for the declaration (PF-L1).
     *
     * Since 2.8 the code references to the class are renamed too (PF-I1): `<old>::class` and other `<old>::` accesses,
     * `new <old>`, `instanceof <old>`, a type, and `\<namespace>\<old>` / `namespace\<old>`. Class names are matched
     * case-insensitively, like PHP does (PF-I10). Never renamed (PL-L2): anything inside a string or heredoc (including
     * interpolated keys), `use` imports, members and constants (`->`, `?->`, `::`, `const`, `case`), function names and
     * calls (`function <old>`, `function &<old>`, `<old>(`), named arguments and labels (`<old>:`).
     *
     * @return string|null null when the code has no such declaration, or more than one
     */
    private function renameClass(string $code, string $old, string $new): ?string
    {
        $tokens = [];
        foreach (token_get_all($code) as $token) {
            $tokens[] = is_array($token) ? [$token[0], $token[1]] : [null, $token];
        }
        $out       = '';
        $found     = 0;
        $previous  = [null, null];
        $namespace = '';
        $inString  = false;
        $inUse     = false;
        foreach ($tokens as $i => [$id, $text]) {
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $out .= $text;
                continue;
            }
            if ($text === '"' || $text === '`' || $id === T_START_HEREDOC || $id === T_END_HEREDOC) {
                $inString = $text === '"' || $text === '`' ? !$inString : $id === T_START_HEREDOC;
            } elseif ($inString) {
                // Interpolated text: never code to rename.
            } elseif ($id === T_USE) {
                // An import (`use A\B;`, `use A\{B as C};`), not a closure's `use (...)`: it ends at its `;`.
                $inUse = $this->next($tokens, $i) !== '(';
            } elseif ($inUse) {
                $inUse = $text !== ';';
            } elseif ($previous[0] === T_NAMESPACE && in_array($id, [T_STRING, T_NAME_QUALIFIED], true)) {
                $namespace = $text;
            } elseif ($id === T_STRING && strcasecmp($text, $old) === 0) {
                // `class Name` declares; `Name::class` and `new class` do not.
                if ($previous[0] === T_CLASS && $previous[1] !== T_DOUBLE_COLON) {
                    $text = $new;
                    ++$found;
                } elseif ($this->isClassReference($previous, $this->next($tokens, $i))) {
                    $text = $new;
                }
            } elseif ($id === T_NAME_FULLY_QUALIFIED && strcasecmp($text, $this->qualified($namespace, $old)) === 0) {
                $text = $this->qualified($namespace, $new);
            } elseif ($id === T_NAME_RELATIVE && strcasecmp($text, 'namespace\\' . $old) === 0) {
                $text = 'namespace\\' . $new;
            }
            $previous = [$id ?? $text, $previous[0]];
            $out .= $text;
        }

        return $found === 1 ? $out : null;
    }

    /**
     * Whether a bare name between these tokens refers to a class.
     *
     * @param array{0: int|string|null, 1: int|string|null} $previous the two significant tokens before it (id, or text)
     * @param string|null $next the significant token after it (text)
     */
    private function isClassReference(array $previous, ?string $next): bool
    {
        if (in_array($previous[0], self::NOT_A_CLASS_NAME, true) || ($previous[0] === '&' && $previous[1] === T_FUNCTION)) {
            return false;
        }

        // `Name:` is a named argument, a label or a `case`; `Name(` without `new` is a function call.
        return $next !== ':' && ($next !== '(' || $previous[0] === T_NEW);
    }

    /**
     * Text of the next significant token after position `$i`.
     *
     * @param list<array{0: int|null, 1: string}> $tokens
     */
    private function next(array $tokens, int $i): ?string
    {
        $j = $i + 1;
        while (isset($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            ++$j;
        }

        return $tokens[$j][1] ?? null;
    }

    /**
     * Fully qualified name of a class of the file's namespace.
     */
    private function qualified(string $namespace, string $class): string
    {
        return '\\' . ($namespace === '' ? '' : $namespace . '\\') . $class;
    }

    /**
     * Undoes a partial run and builds the exception that reports it. Each step is undone from what is on disk, not from
     * how far the run got (PL-L1): a missing source is written back first, and a target is removed only once its source
     * is back.
     *
     * @param list<array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null}> $plan
     * @param int $started steps whose target may have been moved into place (a target that changed since is reported)
     * @param Throwable|null $previous the unexpected error that stopped the run, if any
     */
    private function failure(string $message, array $plan, int $started, ?Throwable $previous = null): FixFailedException
    {
        $problems = [];
        $moved    = 0;
        foreach ($plan as $k => $step) {
            try {
                [$undone, $stepProblems] = $this->undo($step, $k < $started);
                $moved += $undone ? 1 : 0;
                $problems = [...$problems, ...$stepProblems];
            } catch (Throwable $e) {
                // One step that throws must not skip the others: report it and go on.
                $problems[] = sprintf('undoing "%s" -> "%s" failed (%s: %s): check both files, and any hidden ".%s.*" or ".%s.*" temporary file, by hand', $step['from'], $step['to'], $e::class, $e->getMessage(), basename($step['to']), basename($step['from']));
            }
        }
        if ($problems !== []) {
            return new FixFailedException($message . " ROLLBACK INCOMPLETE:\n  " . implode("\n  ", $problems), true, $previous);
        }

        return new FixFailedException($message . ($moved > 0 ? sprintf(' Nothing was changed: the %d migration(s) already moved were moved back.', $moved) : ' Nothing was changed.'), false, $previous);
    }

    /**
     * Undoes one step of the plan from the state of the disk.
     *
     * @param array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null} $step
     * @param bool $started whether this run may have moved its target into place
     *
     * @return array{0: bool, 1: list<string>} whether a moved target was taken back, and what could not be undone
     */
    private function undo(array $step, bool $started): array
    {
        $problems = [];
        if ($step['temp'] !== null && file_exists($step['temp']) && !@unlink($step['temp'])) {
            $problems[] = sprintf('temporary file "%s" could not be removed', $step['temp']);
        }
        $ours = @file_get_contents($step['to']) === $step['rewritten'];
        if (!file_exists($step['from']) && !$this->restore($step)) {
            // The renamed copy may now be the only copy of this migration: it is kept.
            $problems[] = sprintf('"%s" could not be restored; %s', $step['from'], sprintf($ours ? 'its content is in "%s" with the class renamed' : 'its renamed copy "%s" is missing or changed since --fix wrote it: compare or recreate it by hand', $step['to']));

            return [false, $problems];
        }
        if ($ours && !@unlink($step['to'])) {
            $problems[] = sprintf('"%s" could not be removed and duplicates "%s": delete it before running the migrations', $step['to'], $step['from']);
        } elseif (!$ours && $started && file_exists($step['to'])) {
            // Not the file this run wrote any more: someone else's, never deleted.
            $problems[] = sprintf('"%s" changed after --fix wrote it and was not removed: compare it with "%s"', $step['to'], $step['from']);
        }

        return [$ours && $problems === [], $problems];
    }

    /**
     * Writes a removed source back from memory without ever replacing a file: staged owner-only next to it, checked,
     * then published like a renamed copy (PL-L3: the bytes are never readable through a wider mode).
     *
     * @param array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null} $step
     */
    private function restore(array $step): bool
    {
        $temp = $this->createOwnerOnly($step['dir'], basename($step['from']), $created);
        if ($temp === null) {
            return false;
        }
        if ($this->write($temp, $step['code'], $step['perms'], $step['group'])) {
            if (@link($temp, $step['from'])) {
                @unlink($temp);

                return true;
            }
            if (!file_exists($step['from']) && @rename($temp, $step['from'])) {
                return true;
            }
        }
        @unlink($temp);

        return false;
    }

    /**
     * Creates an empty hidden file next to `<name>` that is owner-only from the start (PL-L3).
     *
     * On a local directory, tempnam() creates it with mode 0600 whatever the umask, so no other user can ever open it.
     * Where tempnam() cannot work in that directory (a stream wrapper; it then falls back to the system temporary
     * directory, which is refused here), the file is created with fopen('x') and set to 0600 by {@see write()} before
     * its first byte: no byte is stored while it is wider, but a reader that opened it empty in that instant keeps its
     * handle.
     *
     * @param string|null $created set to the path as soon as the file exists (null while it does not)
     *
     * @return string|null null when no file could be created in the directory
     */
    private function createOwnerOnly(string $dir, string $name, ?string &$created): ?string
    {
        $path = @tempnam($dir, '.' . $name . '.');
        if ($path !== false && dirname($path) === (@realpath($dir) ?: $dir)) {
            return $created = $path;
        }
        if ($path !== false) {
            @unlink($path);
        }
        // Set before the call: an error thrown after fopen() created it must still let the caller remove it.
        $created = $dir . '/.' . $name . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle  = @fopen($created, 'x');
        if ($handle === false) {
            // Not created (it may even be someone else's file of that name): never removed by this run.
            return $created = null;
        }
        fclose($handle);

        return $created;
    }

    /**
     * Writes a file with the given permissions and reads it back: true only for a faithful copy. The group of the
     * source is kept when the process may set it (best effort: an unprivileged process may not own that group).
     */
    private function write(string $path, string $contents, int $perms, false|int $group): bool
    {
        $handle = @fopen($path, 'w');
        if ($handle === false) {
            return false;
        }
        // Owner-only before any byte is written (PF-I4): a new file starts with the umask's mode, often 0644, and must
        // not expose a 0600 migration while it is being written. The final mode is set (and checked) below.
        if (!@chmod($path, 0o600)) {
            fclose($handle);

            return false;
        }
        $written = @fwrite($handle, $contents);
        // Durable before anything is published (PF-L3). A stream that cannot fsync (not a plain file) is accepted.
        $synced = @fflush($handle) && (@fsync($handle) || stream_get_meta_data($handle)['wrapper_type'] !== 'plainfile');
        fclose($handle);
        if ($group !== false) {
            @chgrp($path, $group);
        }

        return $synced && $written === strlen($contents) && @chmod($path, $perms) && @file_get_contents($path) === $contents;
    }
}
