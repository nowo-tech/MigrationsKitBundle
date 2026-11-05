<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Test;

use Nowo\MigrationsKitBundle\Versions\GitMigrationFiles;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;

use function implode;
use function sprintf;

/**
 * PHPUnit helper (2.4, KIT-75) for a check-fast suite, without booting the kernel:
 *
 *     final class MigrationVersionsTest extends TestCase
 *     {
 *         use MonotonicMigrationVersionsTrait;
 *
 *         public function testMigrationsAreMonotonic(): void
 *         {
 *             self::assertMigrationVersionsAreMonotonic(dirname(__DIR__, 2));
 *         }
 *     }
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
trait MonotonicMigrationVersionsTrait
{
    /**
     * Fails when a migration added since `$base` (default: the latest tag) sorts before the newest migration of that ref.
     * Passes (and counts one assertion) when there is no tag yet.
     *
     * @param list<string> $paths migrations directories, relative to `$projectDir`
     */
    public static function assertMigrationVersionsAreMonotonic(string $projectDir, array $paths = ['migrations'], ?string $base = null): void
    {
        $messages = [];
        foreach ((new MonotonicVersionsChecker(new GitMigrationFiles($projectDir), $paths, $base))->check() as $report) {
            if (!$report->isValid()) {
                $messages[] = sprintf('%s: %s sort(s) before %s (newest at %s)', $report->path, implode(', ', $report->violations), $report->baseMax, $report->base);
            }
        }

        static::assertSame([], $messages, "Out-of-order migrations:\n" . implode("\n", $messages));
    }
}
