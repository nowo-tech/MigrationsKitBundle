<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Test\MonotonicMigrationVersionsTrait;
use RuntimeException;

use function basename;
use function bin2hex;
use function chgrp;
use function chmod;
use function count;
use function fclose;
use function file_exists;
use function file_get_contents;
use function filegroup;
use function fileperms;
use function fopen;
use function fwrite;
use function implode;
use function is_file;
use function preg_quote;
use function preg_replace;
use function random_bytes;
use function rename;
use function sprintf;
use function strlen;
use function unlink;

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
     * @throws RuntimeException when the migrations cannot be renamed; nothing was changed unless the message says
     *                          "ROLLBACK INCOMPLETE"
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
     * while committing removes the targets already in place and writes back, from memory, the sources already
     * removed. If that rollback itself fails, the message says "ROLLBACK INCOMPLETE" and lists every file that needs
     * attention; a source that cannot be restored keeps its renamed copy, so no migration is ever lost.
     *
     * @param list<MonotonicVersionsReport> $reports
     *
     * @throws RuntimeException when the migrations cannot be renamed; nothing was changed unless the message says
     *                          "ROLLBACK INCOMPLETE"
     *
     * @return list<array<string, string>> per report, in order: old version => new version
     */
    public function fixAll(array $reports, DateTimeImmutable $now = new DateTimeImmutable()): array
    {
        $plan    = [];
        $renamed = [];
        $seen    = [];
        foreach ($reports as $report) {
            $map      = [];
            $dir      = $this->files->absolute($report->path);
            $existing = $this->files->current($report->path);
            if ($report->baseMax !== null) {
                $existing[] = $report->baseMax;
            }
            foreach ($report->violations as $old) {
                $new        = MigrationVersions::next($existing, $now);
                $existing[] = $new;
                $from       = $dir . '/' . $old . '.php';
                $to         = $dir . '/' . $new . '.php';
                if (isset($seen[$from]) || isset($seen[$to])) {
                    throw new RuntimeException(sprintf('Cannot rename migration "%s": it is listed more than once (the same directory given twice?). Nothing was changed.', $from));
                }
                $seen[$from] = $seen[$to] = true;
                $code        = is_file($from) ? @file_get_contents($from) : false;
                $perms       = $code === false ? false : @fileperms($from);
                if ($code === false || $perms === false || file_exists($to)) {
                    throw new RuntimeException(sprintf('Cannot rename migration "%s" to "%s". Nothing was changed.', $from, $to));
                }
                $rewritten = preg_replace('/\bclass\s+' . preg_quote($old, '/') . '\b/', 'class ' . $new, $code, 1, $count);
                if ($rewritten === null || $count !== 1) {
                    throw new RuntimeException(sprintf('Cannot rename migration "%s" to "%s": it does not declare "class %s", so its file and class names would no longer match. Nothing was changed.', $from, $to, $old));
                }
                $plan[]    = ['dir' => $dir, 'from' => $from, 'to' => $to, 'code' => $code, 'rewritten' => $rewritten, 'perms' => $perms & 0o7777, 'group' => @filegroup($from), 'temp' => null];
                $map[$old] = $new;
            }
            $renamed[] = $map;
        }

        foreach ($plan as $k => $step) {
            $temp   = $step['dir'] . '/.' . basename($step['to']) . '.' . bin2hex(random_bytes(8)) . '.tmp';
            $handle = @fopen($temp, 'x');
            if ($handle !== false) {
                fclose($handle);
                $plan[$k]['temp'] = $temp;
            }
            if ($handle === false || !$this->write($temp, $step['rewritten'], $step['perms'], $step['group'])) {
                throw $this->failure(sprintf('Cannot rename migration "%s" to "%s": the renamed copy could not be written and checked in "%s".', $step['from'], $step['to'], $step['dir']), $plan, 0, 0);
            }
        }

        foreach ($plan as $k => $step) {
            if (file_exists($step['to']) || !@rename((string) $step['temp'], $step['to'])) {
                throw $this->failure(sprintf('Cannot rename migration "%s" to "%s".', $step['from'], $step['to']), $plan, $k, 0);
            }
        }

        foreach ($plan as $k => $step) {
            // A source edited since it was read is not removed: its new bytes would be lost.
            if (@file_get_contents($step['from']) !== $step['code'] || !@unlink($step['from'])) {
                throw $this->failure(sprintf('Cannot remove migration "%s" after writing it as "%s" (it could not be removed, or it changed since --fix read it).', $step['from'], $step['to']), $plan, count($plan), $k);
            }
        }

        return $renamed;
    }

    /**
     * Undoes a partial commit and builds the exception that reports it.
     *
     * @param list<array{dir: string, from: string, to: string, code: string, rewritten: string, perms: int, group: false|int, temp: string|null}> $plan
     * @param int $placed steps whose target is in place
     * @param int $removed steps whose source was removed
     */
    private function failure(string $message, array $plan, int $placed, int $removed): RuntimeException
    {
        $problems = [];
        foreach ($plan as $k => $step) {
            if ($k < $removed && !$this->write($step['from'], $step['code'], $step['perms'], $step['group'])) {
                // The renamed copy is now the only copy of this migration: keep it.
                $problems[] = sprintf('"%s" could not be restored; its content is in "%s" with the class renamed', $step['from'], $step['to']);
                continue;
            }
            if ($k < $placed && !@unlink($step['to'])) {
                $problems[] = sprintf('"%s" could not be removed and duplicates "%s": delete it before running the migrations', $step['to'], $step['from']);
            }
            if ($k >= $placed && $step['temp'] !== null && !@unlink($step['temp'])) {
                $problems[] = sprintf('temporary file "%s" could not be removed', $step['temp']);
            }
        }
        if ($problems !== []) {
            return new RuntimeException($message . " ROLLBACK INCOMPLETE:\n  " . implode("\n  ", $problems));
        }

        return new RuntimeException($message . ($placed > 0 ? sprintf(' Nothing was changed: the %d migration(s) already moved were moved back.', $placed) : ' Nothing was changed.'));
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
        $written = @fwrite($handle, $contents);
        fclose($handle);
        if ($group !== false) {
            @chgrp($path, $group);
        }

        return $written === strlen($contents) && @chmod($path, $perms) && @file_get_contents($path) === $contents;
    }
}
