<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Test\MonotonicMigrationVersionsTrait;
use RuntimeException;

use function file_get_contents;
use function file_put_contents;
use function is_file;
use function preg_quote;
use function preg_replace;
use function rename;
use function sprintf;

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
     * @throws RuntimeException when a file cannot be read or renamed
     *
     * @return array<string, string> old version => new version
     */
    public function fix(MonotonicVersionsReport $report, DateTimeImmutable $now = new DateTimeImmutable()): array
    {
        $dir      = $this->files->absolute($report->path);
        $existing = $this->files->current($report->path);
        if ($report->baseMax !== null) {
            $existing[] = $report->baseMax;
        }
        $renamed = [];
        foreach ($report->violations as $old) {
            $new        = MigrationVersions::next($existing, $now);
            $existing[] = $new;
            $from       = $dir . '/' . $old . '.php';
            $to         = $dir . '/' . $new . '.php';
            $code       = is_file($from) ? file_get_contents($from) : false;
            if ($code === false || is_file($to)) {
                throw new RuntimeException(sprintf('Cannot rename migration "%s" to "%s".', $from, $to));
            }
            $code = (string) preg_replace('/\bclass\s+' . preg_quote($old, '/') . '\b/', 'class ' . $new, $code, 1);
            if (file_put_contents($from, $code) === false || !rename($from, $to)) {
                throw new RuntimeException(sprintf('Cannot rename migration "%s" to "%s".', $from, $to));
            }
            $renamed[$old] = $new;
        }

        return $renamed;
    }
}
