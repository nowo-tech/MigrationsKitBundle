<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Versions\FixFailedException;
use Nowo\MigrationsKitBundle\Versions\GitMigrationFiles;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ValueError;

use function array_keys;
use function chmod;
use function clearstatcache;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function in_array;
use function is_readable;
use function is_writable;
use function ksort;
use function mkdir;
use function scandir;
use function sprintf;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function substr;
use function substr_count;
use function sys_get_temp_dir;
use function umask;
use function uniqid;

/**
 * PB-M-L2: `--fix` over several migrations is all-or-nothing. Every rewritten migration is written to a temporary file
 * next to its target and checked before any of them is moved into place; a failure at any point leaves every original
 * byte-identical (content and permissions), no temporary file behind, and a message that says what happened.
 *
 * The new versions are deterministic: base newest Version20261120170000, so the three new migrations become
 * Version20261120170001..170003.
 */
#[CoversClass(MonotonicVersionsChecker::class)]
#[CoversClass(FixFailedException::class)]
final class MonotonicVersionsAtomicFixTest extends TestCase
{
    private const NEW = ['Version20261105090000', 'Version20261105091000', 'Version20261105092000'];

    private string $root;

    private string $dir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nowo-mk-atomic-' . uniqid();
        $this->dir  = $this->root . '/repo/migrations';
        mkdir($this->dir, 0o777, true);
        foreach (self::NEW as $version) {
            $this->migration($this->dir, $version, 0o640);
        }

        stream_wrapper_register(FaultyStreamWrapper::SCHEME, FaultyStreamWrapper::class);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister(FaultyStreamWrapper::SCHEME);
        @chmod($this->dir, 0o777);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testAllMigrationsAreRenamedWithTheirPermissions(): void
    {
        $renamed = $this->checker()->fix($this->report(), $this->now());

        self::assertSame([
            'Version20261105090000' => 'Version20261120170001',
            'Version20261105091000' => 'Version20261120170002',
            'Version20261105092000' => 'Version20261120170003',
        ], $renamed);
        self::assertSame(['Version20261120170001.php', 'Version20261120170002.php', 'Version20261120170003.php'], array_keys($this->snapshot()));
        foreach ($this->snapshot() as $file => [$code, $perms]) {
            self::assertStringContainsString('final class ' . substr($file, 0, -4) . ' extends', $code);
            self::assertSame(0o640, $perms, $file . ' keeps the permissions of the migration it replaces.');
        }
    }

    public function testAFailureOnTheSecondOfThreeFilesChangesNothing(): void
    {
        // The second migration cannot be read: nothing may move, not even the first migration, which could have.
        $before = $this->snapshot();
        $this->unreadable($this->dir . '/Version20261105091000.php');

        try {
            $this->checker()->fix($this->report(), $this->now());
            self::fail('An unreadable migration must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Cannot rename migration', $e->getMessage());
            self::assertStringContainsString('Version20261105091000.php', $e->getMessage());
        }

        chmod($this->dir . '/Version20261105091000.php', 0o640);
        self::assertSame($before, $this->snapshot());
    }

    public function testARenameRefusedOnTheSecondOfThreeFilesMovesTheFirstOneBack(): void
    {
        FaultyStreamWrapper::faults($this->root, ['failAt' => ['rename' => 2]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A refused rename must be reported.');
        } catch (FixFailedException $e) {
            self::assertFalse($e->rollbackIncomplete);
            self::assertStringContainsString('Cannot rename migration', $e->getMessage());
            self::assertStringContainsString('Version20261105091000.php', $e->getMessage());
            self::assertStringContainsString('Version20261120170002.php', $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
            self::assertStringContainsString('1 migration(s) already moved', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot(), 'Contents, permissions, and no temporary file left behind.');
    }

    public function testAnUnwritableDirectoryChangesNothing(): void
    {
        $before = $this->snapshot();
        chmod($this->dir, 0o555);
        clearstatcache();
        if (is_writable($this->dir)) {
            self::markTestSkipped('Running as a user that can write to a read-only directory (root).');
        }

        try {
            $this->checker()->fix($this->report(), $this->now());
            self::fail('An unwritable directory must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Cannot rename migration', $e->getMessage());
            self::assertStringContainsString('could not be written', $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        chmod($this->dir, 0o777);
        self::assertSame($before, $this->snapshot());
    }

    public function testAStagedFileThatCannotBeCheckedChangesNothing(): void
    {
        // The temporary file is written but its permissions cannot be set: it is not a faithful copy, nothing moves.
        FaultyStreamWrapper::faults($this->root, ['failAt' => ['chmod' => 4]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A staged file that cannot be checked must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Version20261105091000.php', $e->getMessage());
            self::assertStringContainsString('could not be written', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testATemporaryFileThatCannotBeRemovedIsReported(): void
    {
        FaultyStreamWrapper::faults($this->root, ['failAt' => ['chmod' => 4], 'failOn' => ['unlink' => ['.tmp']]]);

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('The failure must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('could not be removed', $e->getMessage());
            self::assertStringContainsString('.Version20261120170001.php.', $e->getMessage());
        }
    }

    public function testASourceThatCannotBeRemovedIsRestoredWithTheOthers(): void
    {
        // Every target is in place; removing the second original fails, after the first one was removed.
        FaultyStreamWrapper::faults($this->root, ['failOn' => ['unlink' => ['Version20261105091000.php']]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A source that cannot be removed must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Cannot remove migration', $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot(), 'The first migration is written back from memory, with its permissions.');
    }

    public function testASourceEditedWhileFixRunsIsNotDeleted(): void
    {
        // The second source is saved by someone else after it was read and staged: removing it would lose that edit.
        FaultyStreamWrapper::faults($this->root, ['after' => [['op' => 'rename', 'call' => 3, 'file' => $this->dir . '/Version20261105091000.php', 'append' => "// edited\n"]]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A source that changed must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('changed since --fix read it', $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        $before['Version20261105091000.php'][0] .= "// edited\n";
        self::assertSame($before, $this->snapshot(), 'The edit is kept and the other migrations are back.');
    }

    public function testATargetThatAppearsBeforeTheCommitIsNeverOverwritten(): void
    {
        // PF-L2: someone creates the second target after it was planned (here: right after the last copy is staged).
        $foreign = $this->dir . '/Version20261120170002.php';
        FaultyStreamWrapper::faults($this->root, ['after' => [['op' => 'chmod', 'call' => 6, 'file' => $foreign, 'append' => 'foreign']]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A target that appeared must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Version20261120170002.php', $e->getMessage());
            self::assertStringContainsString('already exists', $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        $after = $this->snapshot();
        self::assertSame('foreign', $after['Version20261120170002.php'][0], 'The file that appeared is not overwritten, and not deleted by the rollback.');
        unset($after['Version20261120170002.php']);
        self::assertSame($before, $after);
    }

    public function testWithoutHardLinksADirectoryThatCannotBeLockedChangesNothing(): void
    {
        // Through a stream wrapper link() is unavailable, so the fallback locks the directory first; if the lock file
        // cannot be opened, nothing moves.
        FaultyStreamWrapper::faults($this->root, ['failOn' => ['write' => ['/.nowo-migrations-fix.lock']]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A directory that cannot be locked must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('could not be locked', $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testARollbackLeavesATargetSomeoneElseChanged(): void
    {
        // The first target is moved into place, then edited by someone else before the second move fails: the rollback
        // must not delete a file that is no longer the one --fix wrote.
        $first = $this->dir . '/Version20261120170001.php';
        FaultyStreamWrapper::faults($this->root, [
            'failAt' => ['rename' => 2],
            'after'  => [['op' => 'rename', 'call' => 1, 'file' => $first, 'append' => "// edited\n"]],
        ]);

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('The failure must be reported.');
        } catch (FixFailedException $e) {
            self::assertTrue($e->rollbackIncomplete);
            self::assertStringContainsString('ROLLBACK INCOMPLETE', $e->getMessage());
            self::assertStringContainsString('Version20261120170001.php" changed after --fix wrote it', $e->getMessage());
        }

        self::assertStringEndsWith("// edited\n", (string) file_get_contents($first));
    }

    public function testARealDirectoryPublishesWithoutTemporaryFilesLeft(): void
    {
        $this->checker()->fix($this->report(), $this->now());

        self::assertSame(['Version20261120170001.php', 'Version20261120170002.php', 'Version20261120170003.php'], array_keys($this->snapshot()), 'No temporary or lock file is left behind.');
    }

    public function testAMentionInACommentOrAStringIsNotTheDeclaration(): void
    {
        // PF-L1: the first "class Version..." in the file is in a docblock, a comment and a string; only the declaration
        // is renamed, and the mentions are left as written.
        $file = $this->dir . '/Version20261105090000.php';
        file_put_contents($file, <<<'PHP'
            <?php

            /**
             * Follows class Version20261105090000 of the old branch.
             */
            // class Version20261105090000 was generated by make:migration
            # class Version20261105090000
            final class Version20261105090000 extends \Doctrine\Migrations\AbstractMigration
            {
                public function getDescription(): string
                {
                    return 'class Version20261105090000' . self::class . Version20261105090000::class;
                }
            }

            PHP);

        $this->checker()->fix($this->report(), $this->now());

        $code = (string) file_get_contents($this->dir . '/Version20261120170001.php');
        self::assertStringContainsString('final class Version20261120170001 extends', $code);
        self::assertStringContainsString(' * Follows class Version20261105090000 of the old branch.', $code);
        self::assertStringContainsString('// class Version20261105090000 was generated', $code);
        self::assertStringContainsString("# class Version20261105090000\n", $code);
        self::assertStringContainsString("'class Version20261105090000'", $code);
        // PF-I1: the code reference to the class follows the rename; the text mentions above do not.
        self::assertStringContainsString(' . Version20261120170001::class;', $code);
    }

    public function testAMentionInACommentDoesNotBypassTheMismatchGuard(): void
    {
        // PF-L1: the real class is SomethingElse; a comment that names the file's class must not let the rename through.
        file_put_contents($this->dir . '/Version20261105091000.php', "<?php\n\n/* class Version20261105091000 */\n\$s = 'class Version20261105091000';\nfinal class SomethingElse\n{\n}\n");
        $before = $this->snapshot();

        try {
            $this->checker()->fix($this->report(), $this->now());
            self::fail('A migration without its class declaration must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('does not declare "class Version20261105091000"', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testARestoreThatFailsKeepsTheRenamedCopyAndSaysSo(): void
    {
        FaultyStreamWrapper::faults($this->root, ['failOn' => ['unlink' => ['Version20261105091000.php'], 'write' => ['/Version20261105090000.php']]]);

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('A failed restore must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('ROLLBACK INCOMPLETE', $e->getMessage());
            self::assertStringContainsString('Version20261105090000.php" could not be restored', $e->getMessage());
            self::assertStringContainsString('Version20261120170001.php', $e->getMessage());
        }

        // The only copy of the first migration is the renamed one: it must not have been deleted.
        $after = $this->snapshot();
        self::assertArrayHasKey('Version20261120170001.php', $after);
        self::assertArrayNotHasKey('Version20261120170002.php', $after);
        self::assertArrayHasKey('Version20261105091000.php', $after);
        self::assertArrayHasKey('Version20261105092000.php', $after);
    }

    public function testAMovedCopyThatCannotBeRemovedOnRollbackIsReported(): void
    {
        FaultyStreamWrapper::faults($this->root, ['failAt' => ['rename' => 2], 'failOn' => ['unlink' => ['/Version20261120170001.php']]]);

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('The failure must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('ROLLBACK INCOMPLETE', $e->getMessage());
            self::assertStringContainsString('Version20261120170001.php" could not be removed', $e->getMessage());
        }
    }

    public function testAMigrationWhoseClassNameDiffersFromItsFileIsNotRenamed(): void
    {
        // PB-M-I5: renaming the file without the class would produce the mismatch --fix exists to avoid.
        file_put_contents($this->dir . '/Version20261105091000.php', "<?php\n\nfinal class SomethingElse\n{\n}\n");
        $before = $this->snapshot();

        try {
            $this->checker()->fix($this->report(), $this->now());
            self::fail('A migration without its class declaration must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('does not declare "class Version20261105091000"', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testTheSameDirectoryTwiceIsRefused(): void
    {
        $before = $this->snapshot();

        try {
            $this->checker()->fixAll([$this->report(), $this->report()], $this->now());
            self::fail('A migration listed twice must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('listed more than once', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testSeveralDirectoriesAreAllOrNothing(): void
    {
        $tenant = $this->root . '/repo/migrations/tenant';
        mkdir($tenant);
        $this->migration($tenant, 'Version20261105090000', 0o644);
        $tenantReport = new MonotonicVersionsReport('migrations/tenant', 'v1.0.0', 'Version20261120170000', ['Version20261105090000'], ['Version20261105090000']);
        $before       = $this->snapshot();
        $this->unreadable($tenant . '/Version20261105090000.php');

        try {
            $this->checker()->fixAll([$this->report(), $tenantReport], $this->now());
            self::fail('A failure in the second directory must be reported.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('tenant/Version20261105090000.php', $e->getMessage());
        }
        chmod($tenant . '/Version20261105090000.php', 0o644);
        self::assertSame($before, $this->snapshot(), 'The first directory is untouched too.');

        $renamed = $this->checker()->fixAll([$this->report(), $tenantReport], $this->now());
        self::assertCount(2, $renamed);
        self::assertSame('Version20261120170001', $renamed[1]['Version20261105090000']);
        self::assertSame(['Version20261120170001.php', 'Version20261120170002.php', 'Version20261120170003.php'], array_keys($this->snapshot()));
    }

    public function testCodeReferencesToTheClassAreRenamedWithIt(): void
    {
        // PF-I1: a migration that names its own class in code keeps loading after the rename. Members, functions and
        // constants that merely share the name, and every text, are left alone.
        $file = $this->dir . '/Version20261105090000.php';
        file_put_contents($file, <<<'PHP'
            <?php

            namespace DoctrineMigrations;

            final class Version20261105090000 extends \Doctrine\Migrations\AbstractMigration
            {
                public const Version20261105090000 = 'const';

                public function getDescription(): string
                {
                    return Version20261105090000::class . \DoctrineMigrations\Version20261105090000::class . \Other\Version20261105090000::class
                        . $this->Version20261105090000 . $this?->Version20261105090000 . self::Version20261105090000
                        . "Version20261105090000";
                }

                public function copy(): Version20261105090000|false
                {
                    return new Version20261105090000($this->connection, $this->logger) instanceof version20261105090000 ? false : false;
                }

                public function Version20261105090000(): void
                {
                }
            }

            PHP);

        $this->checker()->fix($this->report(), $this->now());

        $code = (string) file_get_contents($this->dir . '/Version20261120170001.php');
        self::assertStringContainsString('final class Version20261120170001 extends', $code);
        self::assertStringContainsString('return Version20261120170001::class . \\DoctrineMigrations\\Version20261120170001::class . \\Other\\Version20261105090000::class', $code);
        self::assertStringContainsString('. $this->Version20261105090000 . $this?->Version20261105090000 . self::Version20261105090000', $code);
        self::assertStringContainsString('. "Version20261105090000";', $code);
        self::assertStringContainsString('public const Version20261105090000 = ', $code);
        self::assertStringContainsString('public function copy(): Version20261120170001|false', $code);
        self::assertStringContainsString('return new Version20261120170001($this->connection, $this->logger) instanceof Version20261120170001 ?', $code);
        self::assertStringContainsString('public function Version20261105090000(): void', $code);
    }

    public function testAReferenceInTheGlobalNamespaceIsRenamed(): void
    {
        file_put_contents($this->dir . '/Version20261105090000.php', "<?php\n\nfinal class Version20261105090000\n{\n    public function me(): string\n    {\n        return \\Version20261105090000::class;\n    }\n}\n");

        $this->checker()->fix($this->report(), $this->now());

        self::assertStringContainsString('return \\Version20261120170001::class;', (string) file_get_contents($this->dir . '/Version20261120170001.php'));
    }

    public function testALowerCaseDeclarationIsRenamedLikePhpReadsIt(): void
    {
        // PF-I10: class names are case-insensitive in PHP, so `class version2026...` declares the file's class.
        file_put_contents($this->dir . '/Version20261105090000.php', "<?php\n\nnamespace DoctrineMigrations;\n\nfinal class version20261105090000 extends \\Doctrine\\Migrations\\AbstractMigration\n{\n}\n");

        $renamed = $this->checker()->fix($this->report(), $this->now());

        self::assertSame('Version20261120170001', $renamed['Version20261105090000']);
        self::assertStringContainsString('final class Version20261120170001 extends', (string) file_get_contents($this->dir . '/Version20261120170001.php'));
    }

    public function testTheSameDirectorySpelledDifferentlyIsRefusedAsListedTwice(): void
    {
        // PF-I2: `migrations` and `./migrations` are one directory; the message says so instead of a generic failure.
        $before = $this->snapshot();
        $dotted = new MonotonicVersionsReport('./migrations', 'v1.0.0', 'Version20261120170000', self::NEW, self::NEW);

        try {
            $this->checker()->fixAll([$this->report(), $dotted], $this->now());
            self::fail('A directory listed twice must be reported.');
        } catch (FixFailedException $e) {
            self::assertFalse($e->rollbackIncomplete);
            self::assertStringContainsString('listed more than once', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testStagedCopiesAreOwnerOnlyWhileTheyAreWritten(): void
    {
        // PF-I4: with a 0600 source, no byte of the copy is ever written while the file has a wider mode.
        foreach (self::NEW as $version) {
            chmod($this->dir . '/' . $version . '.php', 0o600);
        }
        FaultyStreamWrapper::faults($this->root, ['recordModes' => true]);
        // A test process, not a worker: the usual 022 mask makes a new file 0644, restored right after.
        // @phpstan-ignore frankenphp.worker.noUmask
        $umask = umask(0o022);
        try {
            $this->checker(true)->fix($this->report(), $this->now());
        } finally {
            // @phpstan-ignore frankenphp.worker.noUmask
            umask($umask);
        }

        $modes = (string) file_get_contents($this->root . '/' . FaultyStreamWrapper::MODES);
        self::assertSame(3, substr_count($modes, ' 600'), $modes);
        self::assertStringNotContainsString(' 644', $modes);
        foreach ($this->snapshot() as $file => [, $perms]) {
            self::assertSame(0o600, $perms, $file);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: string}>
     */
    public static function unexpectedErrors(): iterable
    {
        // operation, call, phase. Through the stream wrapper: fopen-for-write 1..6 stage the copies, rename 1..3
        // publish them, unlink 1..3 remove the sources.
        yield 'while staging the second copy' => ['write', 3, 'stage'];
        yield 'while publishing the second copy' => ['rename', 2, 'publish'];
        yield 'while removing the second source' => ['unlink', 2, 'remove'];
    }

    #[DataProvider('unexpectedErrors')]
    public function testAnUnexpectedErrorIsRolledBackAndReported(string $operation, int $call, string $phase): void
    {
        // PF-I6: an error that is not a reported failure used to escape fixAll() without any rollback.
        FaultyStreamWrapper::faults($this->root, ['throwAt' => [$operation => $call]]);
        $before = $this->snapshot();

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('The error must be reported (' . $phase . ').');
        } catch (FixFailedException $e) {
            self::assertFalse($e->rollbackIncomplete, $e->getMessage());
            self::assertInstanceOf(ValueError::class, $e->getPrevious());
            self::assertStringContainsString('unexpected ValueError: Injected error on ' . $operation, $e->getMessage());
            self::assertStringContainsString('Nothing was changed', $e->getMessage());
        }

        self::assertSame($before, $this->snapshot(), 'Every original back, no copy or temporary file left (' . $phase . ').');
    }

    public function testAnUnexpectedErrorWhoseRollbackFailsSaysSo(): void
    {
        FaultyStreamWrapper::faults($this->root, ['throwAt' => ['rename' => 2], 'failOn' => ['unlink' => ['/Version20261120170001.php']]]);

        try {
            $this->checker(true)->fix($this->report(), $this->now());
            self::fail('The error must be reported.');
        } catch (FixFailedException $e) {
            self::assertTrue($e->rollbackIncomplete);
            self::assertInstanceOf(ValueError::class, $e->getPrevious());
            self::assertStringContainsString('ROLLBACK INCOMPLETE', $e->getMessage());
        }
    }

    private function checker(bool $faulty = false): MonotonicVersionsChecker
    {
        return new MonotonicVersionsChecker(new GitMigrationFiles(($faulty ? FaultyStreamWrapper::url($this->root) : $this->root) . '/repo'));
    }

    private function report(): MonotonicVersionsReport
    {
        return new MonotonicVersionsReport('migrations', 'v1.0.0', 'Version20261120170000', self::NEW, self::NEW);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-05 10:00:00');
    }

    private function unreadable(string $file): void
    {
        chmod($file, 0o000);
        clearstatcache();
        if (is_readable($file)) {
            self::markTestSkipped('Running as a user that can read a mode 000 file (root).');
        }
    }

    private function migration(string $dir, string $version, int $perms): void
    {
        $file = $dir . '/' . $version . '.php';
        file_put_contents($file, sprintf("<?php\n\nnamespace DoctrineMigrations;\n\nfinal class %s extends \\Doctrine\\Migrations\\AbstractMigration\n{\n}\n", $version));
        chmod($file, $perms);
    }

    /**
     * Every entry of the migrations directory (temporary files included), with its content and permissions.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    private function snapshot(): array
    {
        clearstatcache();
        $files = [];
        foreach (scandir($this->dir) ?: [] as $entry) {
            if (in_array($entry, ['.', '..', 'tenant'], true)) {
                continue;
            }
            $files[$entry] = [(string) file_get_contents($this->dir . '/' . $entry), fileperms($this->dir . '/' . $entry) & 0o7777];
        }
        ksort($files);

        return $files;
    }
}
