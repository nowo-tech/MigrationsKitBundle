<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyFinding;
use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;
use function is_string;
use function sprintf;
use function substr;

/**
 * CI gate: fails when a declared identity or access-key column is not in a binary NO PAD collation (REQ-SEC-006).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:migrations:check-key-collations',
    description: 'Fail (exit 1) when an identity / access-key column declared by an installed bundle is not binary NO PAD',
    help: <<<'HELP'
        Every installed bundle declares its own identity and access-key columns (ExactKeyColumnsInterface, discovered
        automatically). This command reads information_schema for the live connection and fails on any declared column
        that is not binary NO PAD: utf8mb4_0900_bin on MySQL 8.0.17+ / 9.x, utf8mb4_nopad_bin on MariaDB. utf8mb4_bin
        is binary but PAD SPACE ("abc" = "abc   "), accepted only on a server that offers nothing better (MySQL 5.7,
        MySQL 8.0.0-8.0.16), where it is reported as a fallback.

          <info>php %command.full_name%</info>
          <info>php %command.full_name% -v</info>                              also list passing and skipped columns
          <info>php %command.full_name% --connection=legacy</info>

        Columns of a mode that is off are reported as skipped, not failed. A declared, active column whose table or
        column is absent IS a failure: that is a rename or a drop, not a disabled feature. Host ignore entries
        (nowo_migrations_kit.key_collation_check.ignore_columns, each with a reason) never hide a column: it stays in
        the output as "ignored". Exit 0 when nothing fails or the platform has no such notion (PostgreSQL, SQLite),
        1 on any failure, 2 when the database cannot be read.
        HELP,
)]
final class CheckKeyCollationsCommand extends Command
{
    public const EXIT_MISCONFIGURED = 2;

    public function __construct(
        private readonly ConnectionRegistry $connections,
        private readonly ExactKeyChecker $checker,
        private readonly ?string $defaultConnection = null,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Doctrine connection name (default: key_collation_check.connection, else nowo_migrations_kit.connection)');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('connection');
        $name = is_string($name) && $name !== '' ? $name : $this->defaultConnection;

        try {
            $connection = $this->connections->getConnection($name);
            if (!$connection instanceof Connection) {
                throw new InvalidArgumentException(sprintf('Connection "%s" is not a DBAL connection.', (string) $name));
            }
            $report = $this->checker->check($connection);
        } catch (Throwable $e) {
            $output->writeln('<error>' . OutputFormatter::escape($e->getMessage()) . '</error>');

            return self::EXIT_MISCONFIGURED;
        }

        if (!$report->supported) {
            $output->writeln(sprintf('<comment>SKIP: no NO PAD collation question on %s (MySQL / MariaDB only).</comment>', OutputFormatter::escape($report->platform)));

            return Command::SUCCESS;
        }

        if ($report->findings === []) {
            $output->writeln('<comment>No identity / access-key column declared by any installed bundle.</comment>');

            return Command::SUCCESS;
        }

        $failures = $report->failures();
        $ignored  = $report->withStatus(ExactKeyStatus::IGNORED);
        $skipped  = $report->withStatus(ExactKeyStatus::SKIPPED);
        $fallback = $report->withStatus(ExactKeyStatus::FALLBACK);

        $this->write($output, $failures, '<error>');
        $this->write($output, $fallback, '<comment>');
        $this->write($output, $ignored, '<comment>');
        if ($output->isVerbose()) {
            $this->write($output, $skipped, '<comment>');
            $this->write($output, $report->withStatus(ExactKeyStatus::OK), '<info>');
        }

        if ($report->degraded) {
            $output->writeln('<comment>This server offers no NO PAD collation (MySQL 5.7 or 8.0.0-8.0.16): utf8mb4_bin is accepted as the documented fallback.</comment>');
        }

        if ($failures !== []) {
            $output->writeln(sprintf('<error>FAIL: %d declared identity / access-key column(s) are not binary NO PAD on %s (expected %s).</error>', count($failures), $report->platform, OutputFormatter::escape($report->expectedCollation)));
            foreach ($failures as $finding) {
                if ($finding->alter !== null) {
                    $output->writeln('  ' . OutputFormatter::escape($finding->alter));
                }
            }

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>OK: %d declared identity / access-key column(s) from %d bundle(s) on %s (%d ok, %d fallback, %d ignored by host, %d skipped: mode off).</info>',
            count($report->findings),
            $report->declaringBundles,
            $report->platform,
            count($report->withStatus(ExactKeyStatus::OK)),
            count($fallback),
            count($ignored),
            count($skipped),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<ExactKeyFinding> $findings
     */
    private function write(OutputInterface $output, array $findings, string $tag): void
    {
        $close = '</' . substr($tag, 1);
        foreach ($findings as $finding) {
            $output->writeln('  ' . $tag . OutputFormatter::escape($finding->describe()) . $close . ' <comment>[' . OutputFormatter::escape($finding->column->owner) . ']</comment>');
        }
    }
}
