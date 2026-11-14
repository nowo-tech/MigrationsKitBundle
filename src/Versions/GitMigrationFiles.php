<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Versions;

use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function fclose;
use function is_dir;
use function is_resource;
use function ltrim;
use function proc_close;
use function proc_open;
use function rtrim;
use function scandir;
use function sort;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function stream_get_contents;
use function strlen;
use function substr;
use function trim;

/**
 * Migration versions of a directory in the working tree and at a git ref (2.4, KIT-75). Runs `git` without a shell
 * (argument list), in the repository that holds the project.
 *
 * Not marked `final`: a PHPUnit double subclasses it to force the "git could not be started" branch, which no input
 * to proc_open() can produce.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
readonly class GitMigrationFiles
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * The latest tag reachable from HEAD (`git describe --tags --abbrev=0`), or null when there is none.
     */
    public function latestTag(): ?string
    {
        [$code, $out] = $this->git(['describe', '--tags', '--abbrev=0']);

        return $code === 0 && trim($out) !== '' ? trim($out) : null;
    }

    /**
     * Migration versions of a directory in the working tree (relative to the project), sorted by name.
     *
     * @return list<string>
     */
    public function current(string $path): array
    {
        $dir = $this->absolute($path);
        if (!is_dir($dir)) {
            return [];
        }

        return $this->versions(scandir($dir) ?: []);
    }

    /**
     * Migration versions of a directory at a ref (top level of the directory only, like Doctrine's finder).
     *
     * @throws RuntimeException when git fails (not a repository, unknown ref)
     *
     * @return list<string>
     */
    public function atRef(string $ref, string $path): array
    {
        [$code, , $error] = $this->git(['rev-parse', '--verify', '--quiet', $ref . '^{commit}']);
        if ($code !== 0) {
            throw new RuntimeException(sprintf('Unknown git ref "%s"%s.', $ref, trim($error) !== '' ? ': ' . trim($error) : ''));
        }
        $relative             = $this->relative($path);
        [$code, $out, $error] = $this->git(['ls-tree', '--name-only', $ref, $relative === '' ? '.' : $relative . '/']);
        if ($code !== 0) {
            throw new RuntimeException(sprintf('git ls-tree failed for "%s" at "%s": %s', $path, $ref, trim($error)));
        }

        return $this->versions(array_map(basename(...), array_values(array_filter(explode("\n", $out), static fn (string $line): bool => $line !== ''))));
    }

    /**
     * Absolute path of a project-relative directory.
     */
    public function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? rtrim($path, '/') : rtrim($this->projectDir, '/') . '/' . trim($path, '/');
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function versions(array $files): array
    {
        $versions = [];
        foreach ($files as $file) {
            $version = MigrationVersions::versionOf($file);
            if ($version !== null) {
                $versions[] = $version;
            }
        }
        sort($versions);

        return $versions;
    }

    private function relative(string $path): string
    {
        $absolute = $this->absolute($path);
        $root     = rtrim(str_replace('\\', '/', $this->projectDir), '/');

        return str_starts_with($absolute, $root . '/') ? ltrim(substr($absolute, strlen($root)), '/') : trim($path, '/');
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string, 2: string} exit code, stdout, stderr
     */
    private function git(array $arguments): array
    {
        $process = $this->open($arguments, $pipes);
        if (!is_resource($process) || !isset($pipes[1], $pipes[2])) {
            return [127, '', 'git could not be started'];
        }
        $out   = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $error];
    }

    /**
     * Starts `git` with the given argument list. Seam (see the class docblock): a PHPUnit double returns false here.
     *
     * @param list<string> $arguments
     * @param array<int, resource>|null $pipes
     *
     * @return false|resource
     */
    protected function open(array $arguments, ?array &$pipes)
    {
        // Console command / test helper only (never in a request); git returns promptly on a local repository.
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout
        return proc_open(['git', '-C', $this->projectDir, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    }
}
