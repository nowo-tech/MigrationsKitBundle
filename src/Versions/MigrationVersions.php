<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

use DateTimeImmutable;
use DateTimeInterface;

use function array_diff;
use function array_values;
use function basename;
use function ctype_digit;
use function max;
use function preg_match;
use function sort;
use function strcmp;
use function strlen;
use function substr;

/**
 * Version rules of Doctrine migration classes (2.4, KIT-75): Doctrine runs migrations of one namespace in alphabetical
 * order of their class name, so a migration added after a release must sort **after** every migration that release
 * already had — else production (which already ran the later ones) runs it last while a fresh install runs it early.
 *
 * Pure functions on class short names (`Version20261105120000`); no I/O.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MigrationVersions
{
    /** Migration class file names: `Version<suffix>.php` (Doctrine's generator writes `Version` + `YmdHis`). */
    private const FILE_PATTERN = '/^(Version[A-Za-z0-9_]+)\.php$/';

    /**
     * The class short name of a migration file (`Version20261105120000`), or null for any other file.
     */
    public static function versionOf(string $path): ?string
    {
        return preg_match(self::FILE_PATTERN, basename($path), $match) === 1 ? $match[1] : null;
    }

    /**
     * The greatest version (alphabetical, Doctrine's order), or null for none.
     *
     * @param list<string> $versions
     */
    public static function max(array $versions): ?string
    {
        $max = null;
        foreach ($versions as $version) {
            if ($max === null || strcmp($version, $max) > 0) {
                $max = $version;
            }
        }

        return $max;
    }

    /**
     * Versions present now but not at the base, sorted.
     *
     * @param list<string> $base
     * @param list<string> $current
     *
     * @return list<string>
     */
    public static function added(array $base, array $current): array
    {
        $added = array_values(array_diff($current, $base));
        sort($added);

        return $added;
    }

    /**
     * Added versions that do not sort after the greatest base version (they would run out of order), sorted.
     *
     * @param list<string> $base
     * @param list<string> $current
     *
     * @return list<string>
     */
    public static function violations(array $base, array $current): array
    {
        $max = self::max($base);
        if ($max === null) {
            return [];
        }
        $violations = [];
        foreach (self::added($base, $current) as $version) {
            if (strcmp($version, $max) <= 0) {
                $violations[] = $version;
            }
        }

        return $violations;
    }

    /**
     * The next version that sorts after every given one: `Version` + the current `YmdHis`, or, when an existing
     * version is in the future (or the same second), the greatest timestamp version + 1 second.
     *
     * @param list<string> $versions
     */
    public static function next(array $versions, DateTimeInterface $now): string
    {
        $candidate = (int) $now->format('YmdHis');
        foreach ($versions as $version) {
            $digits = substr($version, strlen('Version'));
            if (strlen($digits) === 14 && ctype_digit($digits)) {
                $candidate = max($candidate, self::plusOneSecond($digits));
            }
        }
        $next = 'Version' . $candidate;
        $max  = self::max($versions);

        // Non-timestamp names (Version_abc) sort after digits: append to stay after them.
        return $max !== null && strcmp($next, $max) <= 0 ? $max . '1' : $next;
    }

    private static function plusOneSecond(string $digits): int
    {
        $time = DateTimeImmutable::createFromFormat('!YmdHis', $digits);

        // Not a real date (e.g. Version99999999999998): plain numeric successor.
        return $time === false || $time->format('YmdHis') !== $digits ? (int) $digits + 1 : (int) $time->modify('+1 second')->format('YmdHis');
    }
}
