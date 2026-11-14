<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Versions;

use Nowo\MigrationsKitBundle\Command\CheckMigrationVersionsCommand;
use Nowo\MigrationsKitBundle\Versions\GitMigrationFiles;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function escapeshellarg;
use function exec;
use function file_put_contents;
use function mkdir;
use function sprintf;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function sys_get_temp_dir;
use function uniqid;

/**
 * PF-I5 / PB-M-I1 for check-versions: without --strict, "nothing compared" and "all fine" are both 0, and "nothing
 * changed" and "ROLLBACK INCOMPLETE" are both 2. --strict (2.8) tells them apart; the default codes do not change.
 *
 * The base ref is fixed (no git): the directory is reached through {@see FaultyStreamWrapper} to force an incomplete
 * rollback, which a real filesystem cannot produce on demand.
 */
#[CoversClass(CheckMigrationVersionsCommand::class)]
final class CheckMigrationVersionsStrictTest extends TestCase
{
    private const NEW = ['Version20261105090000', 'Version20261105091000', 'Version20261105092000'];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nowo-mk-strict-' . uniqid();
        mkdir($this->root . '/repo/migrations', 0o777, true);
        mkdir($this->root . '/repo/empty', 0o777, true);
        foreach (['Version20991120170000', ...self::NEW] as $version) {
            file_put_contents($this->root . '/repo/migrations/' . $version . '.php', sprintf("<?php\n\nnamespace DoctrineMigrations;\n\nfinal class %s extends \\Doctrine\\Migrations\\AbstractMigration\n{\n}\n", $version));
        }
        stream_wrapper_register(FaultyStreamWrapper::SCHEME, FaultyStreamWrapper::class);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister(FaultyStreamWrapper::SCHEME);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testAnIncompleteRollbackExitsFourOnlyWithStrict(): void
    {
        $this->incompleteRollback();
        $tester = $this->tester();

        self::assertSame(CheckMigrationVersionsCommand::EXIT_ROLLBACK_INCOMPLETE, $tester->execute(['--fix' => true, '--strict' => true]));
        self::assertStringContainsString('ROLLBACK INCOMPLETE', $tester->getDisplay());
    }

    public function testAnIncompleteRollbackStillExitsTwoWithoutStrict(): void
    {
        $this->incompleteRollback();
        $tester = $this->tester();

        self::assertSame(CheckMigrationVersionsCommand::EXIT_MISCONFIGURED, $tester->execute(['--fix' => true]));
        self::assertStringContainsString('ROLLBACK INCOMPLETE', $tester->getDisplay());
    }

    public function testAFixThatChangedNothingExitsTwoWithStrict(): void
    {
        FaultyStreamWrapper::faults($this->root, ['failAt' => ['rename' => 2]]);
        $tester = $this->tester();

        self::assertSame(CheckMigrationVersionsCommand::EXIT_MISCONFIGURED, $tester->execute(['--fix' => true, '--strict' => true]));
        self::assertStringContainsString('Nothing was changed', $tester->getDisplay());
    }

    public function testAFixThatSucceedsExitsZeroWithStrict(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--fix' => true, '--strict' => true]));
        self::assertStringContainsString('Renamed migrations/Version20261105090000 → Version20991120170001', $tester->getDisplay());
    }

    public function testAnOutOfOrderMigrationIsOneWithStrict(): void
    {
        self::assertSame(Command::FAILURE, $this->tester()->execute(['--strict' => true, '--path' => ['migrations', 'empty']]));
    }

    public function testADirectoryWithoutAnyMigrationIsNothingCheckedWithStrict(): void
    {
        $tester = $this->tester();

        self::assertSame(CheckMigrationVersionsCommand::EXIT_NOTHING_CHECKED, $tester->execute(['--strict' => true, '--path' => ['empty']]));
        self::assertStringContainsString('EMPTY empty: no migration at v1.0.0 nor in the working tree (is the path right?).', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--path' => ['empty']]), 'Unchanged without --strict.');
    }

    public function testNoBaseRefIsNothingCheckedWithStrict(): void
    {
        $tester = new CommandTester(new CheckMigrationVersionsCommand(new MonotonicVersionsChecker(new FixedRefMigrationFiles(FaultyStreamWrapper::url($this->root) . '/repo', null))));

        self::assertSame(CheckMigrationVersionsCommand::EXIT_NOTHING_CHECKED, $tester->execute(['--strict' => true]));
        self::assertSame(Command::SUCCESS, $tester->execute([]), 'Unchanged without --strict.');
    }

    private function incompleteRollback(): void
    {
        // The second move fails; the first target was edited by someone else meanwhile, so the rollback leaves it.
        FaultyStreamWrapper::faults($this->root, [
            'failAt' => ['rename' => 2],
            'after'  => [['op' => 'rename', 'call' => 1, 'file' => $this->root . '/repo/migrations/Version20991120170001.php', 'append' => "// edited\n"]],
        ]);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new CheckMigrationVersionsCommand(new MonotonicVersionsChecker(new FixedRefMigrationFiles(FaultyStreamWrapper::url($this->root) . '/repo', 'v1.0.0'))));
    }
}

/**
 * A base ref without git: v1.0.0 holds Version20991120170000 in `migrations` and nothing elsewhere.
 *
 * @internal
 */
final readonly class FixedRefMigrationFiles extends GitMigrationFiles
{
    public function __construct(string $projectDir, private ?string $tag)
    {
        parent::__construct($projectDir);
    }

    public function latestTag(): ?string
    {
        return $this->tag;
    }

    /**
     * {@inheritdoc}
     */
    public function atRef(string $ref, string $path): array
    {
        return $path === 'migrations' ? ['Version20991120170000'] : [];
    }
}
