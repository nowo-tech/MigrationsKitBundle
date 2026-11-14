<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Command\CheckMigrationVersionsCommand;
use Nowo\MigrationsKitBundle\Test\MonotonicMigrationVersionsTrait;
use Nowo\MigrationsKitBundle\Versions\GitMigrationFiles;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_diff;
use function array_values;
use function chmod;
use function clearstatcache;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function is_writable;
use function mkdir;
use function scandir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * KIT-75 against a real git repository: a migration added after the latest tag with a version below that tag's
 * newest migration fails the command and the PHPUnit helper; --fix renames it after the newest one.
 */
final class MonotonicVersionsGitTest extends TestCase
{
    use MonotonicMigrationVersionsTrait;

    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/nowo-mk-versions-' . uniqid();
        mkdir($this->repo . '/migrations', 0o777, true);
        $this->git('init -q');
        $this->git('config user.email dev@example.com');
        $this->git('config user.name Dev');
        $this->git('config commit.gpgsign false');
        $this->migration('Version20261101000000');
        $this->migration('Version20991120170000');
        file_put_contents($this->repo . '/migrations/.gitignore', '');
        $this->git('add -A');
        $this->git('commit -q -m release');
        $this->git('tag v1.0.0');
    }

    protected function tearDown(): void
    {
        @chmod($this->repo . '/migrations', 0o777);
        exec('rm -rf ' . escapeshellarg($this->repo));
    }

    public function testNoTagYetPasses(): void
    {
        $empty = sys_get_temp_dir() . '/nowo-mk-versions-empty-' . uniqid();
        mkdir($empty);
        exec(sprintf('git -C %s init -q', escapeshellarg($empty)));
        self::assertSame([], (new MonotonicVersionsChecker(new GitMigrationFiles($empty)))->check());
        self::assertSame(Command::SUCCESS, $this->tester($empty)->execute([]));
        self::assertMigrationVersionsAreMonotonic($empty);
        exec('rm -rf ' . escapeshellarg($empty));
    }

    public function testMigrationsAfterTheTagPass(): void
    {
        $this->migration('Version20991121000000');
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 migration(s) added since v1.0.0, all after Version20991120170000', $tester->getDisplay());
        self::assertMigrationVersionsAreMonotonic($this->repo);
    }

    public function testAMigrationBeforeTheNewestOfTheTagFails(): void
    {
        $this->migration('Version20261105090000');
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Version20261105090000', $tester->getDisplay());
        self::assertStringContainsString('Version20991120170000', $tester->getDisplay());

        try {
            self::assertMigrationVersionsAreMonotonic($this->repo);
            self::fail('The helper must fail.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('migrations: Version20261105090000 sort(s) before Version20991120170000 (newest at v1.0.0)', $e->getMessage());
        }
    }

    public function testCommittedButUntaggedMigrationsAreCheckedAgainstTheTagOrAnExplicitBase(): void
    {
        $this->migration('Version20261105090000');
        $this->git('add -A');
        $this->git('commit -q -m new');

        self::assertSame(Command::FAILURE, $this->tester()->execute([]));
        self::assertSame(Command::SUCCESS, $this->tester()->execute(['--base' => 'HEAD']), 'Nothing added since HEAD.');
        self::assertSame(Command::SUCCESS, $this->tester()->execute(['--path' => ['other']]), 'An empty directory.');
    }

    public function testUnknownBaseIsMisconfigured(): void
    {
        $tester = $this->tester();

        self::assertSame(CheckMigrationVersionsCommand::EXIT_MISCONFIGURED, $tester->execute(['--base' => 'no-such-ref']));
        self::assertStringContainsString('Unknown git ref "no-such-ref"', $tester->getDisplay());
        $this->expectException(RuntimeException::class);
        (new GitMigrationFiles('/nonexistent-' . uniqid()))->atRef('HEAD', 'migrations');
    }

    public function testFixRenamesTheNewMigrationsAfterTheNewest(): void
    {
        $this->migration('Version20261105090000');
        $this->migration('Version20261105100000');
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--fix' => true]));
        self::assertStringContainsString('Renamed migrations/Version20261105090000', $tester->getDisplay());
        self::assertFalse(is_file($this->repo . '/migrations/Version20261105090000.php'));
        self::assertTrue(is_file($this->repo . '/migrations/Version20991120170001.php'));
        self::assertTrue(is_file($this->repo . '/migrations/Version20991120170002.php'));
        self::assertStringContainsString('final class Version20991120170001 extends', (string) file_get_contents($this->repo . '/migrations/Version20991120170001.php'));
        self::assertTrue(is_file($this->repo . '/migrations/Version20991120170000.php'), 'Released migrations are never touched.');
        self::assertSame(Command::SUCCESS, $this->tester()->execute([]));
    }

    public function testFixFailsWhenTheFileIsGone(): void
    {
        $this->migration('Version20261105090000');
        $checker = new MonotonicVersionsChecker(new GitMigrationFiles($this->repo));
        $report  = $checker->check()[0];
        unlink($this->repo . '/migrations/Version20261105090000.php');

        $this->expectException(RuntimeException::class);
        $checker->fix($report, new DateTimeImmutable('2026-11-05 10:00:00'));
    }

    public function testAFixThatCannotFinishExitsTwoAndChangesNothing(): void
    {
        // PB-M-L2: the failure used to escape the command as an uncaught exception (exit 1, the "out of order" code)
        // after the earlier migrations had already moved. It is now reported, nothing moves, and the exit code is the
        // documented 2.
        $this->migration('Version20261105090000');
        $this->migration('Version20261105100000');
        $before = [file_get_contents($this->repo . '/migrations/Version20261105090000.php'), file_get_contents($this->repo . '/migrations/Version20261105100000.php')];
        chmod($this->repo . '/migrations', 0o555);
        clearstatcache();
        if (is_writable($this->repo . '/migrations')) {
            chmod($this->repo . '/migrations', 0o777);
            self::markTestSkipped('Running as a user that can write to a read-only directory (root).');
        }

        $tester = $this->tester();
        $code   = $tester->execute(['--fix' => true]);
        chmod($this->repo . '/migrations', 0o777);

        self::assertSame(CheckMigrationVersionsCommand::EXIT_MISCONFIGURED, $code);
        self::assertStringContainsString('Cannot rename migration', $tester->getDisplay());
        self::assertStringNotContainsString('Renamed', $tester->getDisplay());
        self::assertSame($before, [file_get_contents($this->repo . '/migrations/Version20261105090000.php'), file_get_contents($this->repo . '/migrations/Version20261105100000.php')]);
        self::assertSame(['.gitignore', 'Version20261101000000.php', 'Version20261105090000.php', 'Version20261105100000.php', 'Version20991120170000.php'], array_values(array_diff(scandir($this->repo . '/migrations') ?: [], ['.', '..'])));
    }

    public function testFixAcrossSeveralDirectoriesReportsEachRename(): void
    {
        mkdir($this->repo . '/tenant');
        $this->migration('Version20261105090000');
        file_put_contents($this->repo . '/tenant/Version20991120170000.php', "<?php\nfinal class Version20991120170000\n{\n}\n");
        $this->git('add -A');
        $this->git('commit -q -m tenant');
        $this->git('tag v1.1.0');
        file_put_contents($this->repo . '/tenant/Version20261105090000.php', "<?php\nfinal class Version20261105090000\n{\n}\n");
        $this->migration('Version20261105100000');
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--fix' => true, '--path' => ['migrations', 'tenant']]));
        self::assertStringContainsString('Renamed migrations/Version20261105100000', $tester->getDisplay());
        self::assertStringContainsString('Renamed tenant/Version20261105090000', $tester->getDisplay());
        self::assertTrue(is_file($this->repo . '/tenant/Version20991120170001.php'));
    }

    public function testAPathGitReadsAsAnOptionIsReportedRatherThanIgnored(): void
    {
        // --path comes from the command line, so "--migrations" reaches git as an unknown option. git fails, and the
        // failure has to surface: a gate that quietly treated it as "no migrations here" would pass by looking at
        // nothing. No shell is involved - the arguments are an argv list - so this is a report, not an injection.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('git ls-tree failed for "--migrations" at "HEAD"');

        (new GitMigrationFiles($this->repo))->atRef('HEAD', '--migrations');
    }

    public function testGitThatCannotBeStartedIsReportedWithItsOwnMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown git ref "HEAD": git could not be started');

        (new UnstartableGitMigrationFiles($this->repo))->atRef('HEAD', 'migrations');
    }

    private function tester(?string $dir = null): CommandTester
    {
        return new CommandTester(new CheckMigrationVersionsCommand(new MonotonicVersionsChecker(new GitMigrationFiles($dir ?? $this->repo))));
    }

    private function migration(string $version): void
    {
        file_put_contents($this->repo . '/migrations/' . $version . '.php', "<?php\n\nnamespace DoctrineMigrations;\n\nfinal class " . $version . " extends \\Doctrine\\Migrations\\AbstractMigration\n{\n}\n");
    }

    private function git(string $arguments): void
    {
        exec(sprintf('git -C %s %s 2>&1', escapeshellarg($this->repo), $arguments), $output, $code);
        self::assertSame(0, $code, implode("\n", $output));
    }
}

/**
 * Forces the "git could not be started" branch: no argument list can make proc_open() itself fail.
 *
 * @internal
 */
final readonly class UnstartableGitMigrationFiles extends GitMigrationFiles
{
    /**
     * {@inheritdoc}
     */
    protected function open(array $arguments, ?array &$pipes)
    {
        return false;
    }
}
