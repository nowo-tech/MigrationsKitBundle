<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Versions;

use function chmod;
use function fclose;
use function feof;
use function fflush;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fread;
use function fstat;
use function fwrite;
use function is_file;
use function json_decode;
use function json_encode;
use function ltrim;
use function max;
use function rawurldecode;
use function rawurlencode;
use function rename;
use function scandir;
use function stat;
use function str_ends_with;
use function strlen;
use function strpbrk;
use function strpos;
use function substr;
use function unlink;

use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const STREAM_META_ACCESS;

/**
 * A stream wrapper over the real filesystem that fails chosen operations, to drive `--fix` into the failures a test
 * cannot produce on a normal filesystem (a rename refused half-way, a source that cannot be removed, a restore that
 * cannot be written).
 *
 * The faults of a root are set with {@see faults()}; without them every operation goes through.
 *
 * Operations: `rename` (matched on the target), `unlink`, `write` (an fopen() that writes), `chmod`.
 *
 * @internal
 */
final class FaultyStreamWrapper
{
    public const SCHEME = 'mkfaulty';

    private const FAULTS = 'faults.json';

    /** @var resource|null set by PHP for stream contexts; unused here */
    public $context;

    /** @var false|resource */
    private $handle = false;

    /** @var list<string> */
    private array $entries = [];

    private int $cursor = 0;

    /**
     * Base URL for a real directory: the directory itself is the URL's host, percent-encoded.
     */
    public static function url(string $root): string
    {
        return self::SCHEME . '://' . rawurlencode($root);
    }

    public function stream_open(string $path, string $mode): bool
    {
        if (strpbrk($mode, 'waxc+') !== false && $this->fails('write', $path)) {
            return false;
        }
        $this->handle = @fopen($this->real($path), $mode);

        return $this->handle !== false;
    }

    public function stream_read(int $count): string
    {
        return $this->handle === false ? '' : (string) fread($this->handle, max(1, $count));
    }

    public function stream_write(string $data): int
    {
        return $this->handle === false ? 0 : (int) fwrite($this->handle, $data);
    }

    public function stream_eof(): bool
    {
        return $this->handle === false || feof($this->handle);
    }

    public function stream_flush(): bool
    {
        return $this->handle !== false && fflush($this->handle);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return $this->handle === false ? false : fstat($this->handle);
    }

    public function stream_close(): void
    {
        if ($this->handle !== false) {
            fclose($this->handle);
            $this->handle = false;
        }
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        if ($option !== STREAM_META_ACCESS || $this->fails('chmod', $path)) {
            return false;
        }

        return @chmod($this->real($path), (int) $value);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path): array|false
    {
        return @stat($this->real($path));
    }

    public function unlink(string $path): bool
    {
        return !$this->fails('unlink', $path) && @unlink($this->real($path));
    }

    public function rename(string $from, string $to): bool
    {
        if ($this->fails('rename', $to) || !@rename($this->real($from), $this->real($to))) {
            return false;
        }
        // Simulates someone saving a file while --fix runs: `editAfterRename` maps a target suffix to a real file.
        foreach ($this->read($this->root($to))['editAfterRename'] ?? [] as $suffix => $file) {
            if (str_ends_with($to, $suffix)) {
                file_put_contents($file, "// edited\n", FILE_APPEND);
            }
        }

        return true;
    }

    public function dir_opendir(string $path): bool
    {
        $entries = @scandir($this->real($path));
        if ($entries === false) {
            return false;
        }
        $this->entries = $entries;
        $this->cursor  = 0;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return $this->entries[$this->cursor++] ?? false;
    }

    public function dir_rewinddir(): bool
    {
        $this->cursor = 0;

        return true;
    }

    public function dir_closedir(): bool
    {
        $this->entries = [];
        $this->cursor  = 0;

        return true;
    }

    /**
     * Writes the faults for a root: `failAt` (operation => fail on the Nth call, 1-based) and `failOn` (operation =>
     * fail when the path ends with one of the suffixes).
     *
     * @param array{failAt?: array<string, int>, failOn?: array<string, list<string>>, editAfterRename?: array<string, string>} $faults
     */
    public static function faults(string $root, array $faults): void
    {
        file_put_contents($root . '/' . self::FAULTS, json_encode($faults + ['calls' => []], JSON_THROW_ON_ERROR));
    }

    /**
     * Counts the call and answers whether it fails. The rules and the counters live in a file of the root, so nothing
     * is shared between tests (and no static state is needed: PHP builds a new wrapper instance per operation).
     */
    private function fails(string $operation, string $url): bool
    {
        $file = $this->root($url) . '/' . self::FAULTS;
        if (!is_file($file)) {
            return false;
        }
        $faults                      = $this->read($this->root($url));
        $call                        = ($faults['calls'][$operation] ?? 0) + 1;
        $faults['calls'][$operation] = $call;
        file_put_contents($file, json_encode($faults, JSON_THROW_ON_ERROR));
        if (($faults['failAt'][$operation] ?? 0) === $call) {
            return true;
        }
        foreach ($faults['failOn'][$operation] ?? [] as $suffix) {
            if (str_ends_with($url, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{failAt?: array<string, int>, failOn?: array<string, list<string>>, editAfterRename?: array<string, string>, calls: array<string, int>}
     */
    private function read(string $root): array
    {
        $file = $root . '/' . self::FAULTS;
        if (!is_file($file)) {
            return ['calls' => []];
        }
        /** @var array{failAt?: array<string, int>, failOn?: array<string, list<string>>, editAfterRename?: array<string, string>, calls: array<string, int>} $faults */
        $faults = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return $faults;
    }

    private function root(string $url): string
    {
        $path  = ltrim(substr($url, strlen(self::SCHEME . '://')), '/');
        $slash = strpos($path, '/');

        return rawurldecode($slash === false ? $path : substr($path, 0, $slash));
    }

    private function real(string $url): string
    {
        $path  = ltrim(substr($url, strlen(self::SCHEME . '://')), '/');
        $slash = strpos($path, '/');

        return $slash === false ? rawurldecode($path) : rawurldecode(substr($path, 0, $slash)) . substr($path, $slash);
    }
}
