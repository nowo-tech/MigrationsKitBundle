<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Command;

use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_values;
use function count;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * CI gate (2.4, KIT-75): fails when a migration added since a base ref does not sort after the migrations of that ref.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:migrations:check-versions',
    description: 'Fail (exit 1) when a migration added since the latest tag (or --base) sorts before migrations that tag already had',
    help: <<<'HELP'
        Doctrine runs migrations in the alphabetical order of their class names. A migration generated today
        (Version20261105…) sorts before one already applied in production with a future version (Version20261120…):
        production runs it last, a fresh install runs it early, and schema dependencies break.

          <info>php %command.full_name%</info>                         latest tag, nowo_migrations_kit.monotonic_versions.paths
          <info>php %command.full_name% --base=origin/main</info>
          <info>php %command.full_name% --path=migrations --path=migrations/tenant</info>
          <info>php %command.full_name% --fix</info>                   rename the offending (new) migrations after the newest one

        Exit 0 when every added migration sorts after the base (or there is no tag yet), 1 on any out-of-order migration,
        2 when git or the base ref cannot be read or --fix cannot rename. --fix renames only migrations added since the
        base (file and class name), all or nothing: run it before they are pushed or applied anywhere.
        HELP,
)]
final class CheckMigrationVersionsCommand extends Command
{
    public const EXIT_MISCONFIGURED = 2;

    public function __construct(
        private readonly MonotonicVersionsChecker $checker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'Git ref to compare with (default: configured base, else the latest tag)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Migrations directory (relative to the project); default: configured paths')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Rename out-of-order migrations added since the base after the newest one');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $base  = $input->getOption('base');
        $paths = $input->getOption('path');
        try {
            $reports = $this->checker->check(is_array($paths) && $paths !== [] ? array_values($paths) : null, is_string($base) && $base !== '' ? $base : null);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return self::EXIT_MISCONFIGURED;
        }
        if ($reports === []) {
            $io->note('No base ref (no tag yet and no --base): nothing to compare.');

            return Command::SUCCESS;
        }

        $failed = 0;
        $toFix  = [];
        foreach ($reports as $report) {
            if ($report->isValid()) {
                $io->writeln(sprintf('<info>OK</info> %s: %d migration(s) added since %s, all after %s.', $report->path, count($report->added), $report->base, $report->baseMax ?? '(none)'));
                continue;
            }
            if ($input->getOption('fix') === true) {
                $toFix[] = $report;
                continue;
            }
            ++$failed;
            $io->error(sprintf(
                "%s: migration(s) added since %s sort before %s, the newest migration of %s:\n  %s\nRename them after it (or run with --fix).",
                $report->path,
                $report->base,
                $report->baseMax,
                $report->base,
                implode("\n  ", $report->violations),
            ));
        }
        if ($toFix !== []) {
            // One all-or-nothing operation over every directory (PB-M-L2): a failure renames nothing.
            try {
                $renamed = $this->checker->fixAll($toFix);
            } catch (RuntimeException $e) {
                $io->error($e->getMessage());

                return self::EXIT_MISCONFIGURED;
            }
            foreach ($toFix as $index => $report) {
                foreach ($renamed[$index] as $old => $new) {
                    $io->writeln(sprintf('<comment>Renamed</comment> %s/%s → %s', $report->path, $old, $new));
                }
            }
        }

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
