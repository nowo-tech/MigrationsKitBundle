<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Versions\GitMigrationFiles;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;

/**
 * `--fix` on a migrations directory whose filesystem refuses the rename: a read-only mount, a full disk, a container
 * volume that only allows writes. The rename cannot be made to fail from a test on a normal filesystem, so the
 * directory is reached through a stream wrapper that writes for real and answers false to rename().
 *
 * What this pins is not the exception but the state it leaves behind: fix() rewrites the class name inside the file
 * before renaming it, so a failed rename used to leave a migration whose class no longer matched its file name -
 * something Doctrine cannot load, produced by a command whose whole job is to keep the directory loadable.
 */
#[CoversClass(MonotonicVersionsChecker::class)]
final class MonotonicVersionsFixFailureTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nowo-mk-fixfail-' . uniqid();
        mkdir($this->root . '/repo/migrations', 0o777, true);

        stream_wrapper_register(FailingRenameStreamWrapper::SCHEME, FailingRenameStreamWrapper::class);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister(FailingRenameStreamWrapper::SCHEME);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testAFailedRenameReportsAndLeavesTheMigrationExactlyAsItWas(): void
    {
        $file     = $this->root . '/repo/migrations/Version20261105090000.php';
        $original = "<?php\n\nnamespace DoctrineMigrations;\n\nfinal class Version20261105090000 extends \\Doctrine\\Migrations\\AbstractMigration\n{\n}\n";
        file_put_contents($file, $original);

        $checker = new MonotonicVersionsChecker(new GitMigrationFiles(FailingRenameStreamWrapper::url($this->root) . '/repo'));
        $report  = new MonotonicVersionsReport('migrations', 'v1.0.0', 'Version20261120170000', ['Version20261105090000'], ['Version20261105090000']);

        try {
            $checker->fix($report, new DateTimeImmutable('2026-11-05 10:00:00'));
            self::fail('A rename the filesystem refuses must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Cannot rename migration', $e->getMessage());
            self::assertStringContainsString('Version20261105090000.php', $e->getMessage());
            self::assertStringContainsString('Version20261120170001.php', $e->getMessage());
        }

        self::assertSame($original, file_get_contents($file), 'A --fix that could not rename must not leave a migration whose class name no longer matches its file name.');
    }
}

/**
 * Passes every operation through to the real filesystem, except rename(), which fails.
 *
 * The real base directory travels inside the URL rather than in a static property, so nothing in the wrapper is
 * shared between tests.
 *
 * @internal
 */
final class FailingRenameStreamWrapper
{
    public const SCHEME = 'mkfail';

    /** @var resource|null set by PHP for stream contexts; unused here */
    public $context;

    /** @var false|resource */
    private $handle = false;

    /** @var list<string> */
    private array $entries = [];

    private int $cursor = 0;

    public function stream_open(string $path, string $mode): bool
    {
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

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return @stat($this->real($path));
    }

    public function dir_opendir(string $path, int $options): bool
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
     * The whole point of the wrapper: the write succeeds, the rename does not.
     */
    public function rename(string $from, string $to): bool
    {
        return false;
    }

    /**
     * Base URL for a real directory: the directory itself is the URL's host, percent-encoded, so nothing in the
     * wrapper is shared between tests.
     */
    public static function url(string $root): string
    {
        return self::SCHEME . '://' . rawurlencode($root);
    }

    private function real(string $url): string
    {
        $path  = ltrim(substr($url, strlen(self::SCHEME . '://')), '/');
        $slash = strpos($path, '/');

        return $slash === false ? rawurldecode($path) : rawurldecode(substr($path, 0, $slash)) . substr($path, $slash);
    }
}
