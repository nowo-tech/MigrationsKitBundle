<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function sprintf;

/**
 * CI gate: fails when a table or string column of the database is not in the expected collation.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsCommand(
    name: 'nowo:migrations:check-collations',
    description: 'Fail (exit 1) when a table or string column is not in the expected collation (MySQL / MariaDB, PostgreSQL)',
    help: <<<'HELP'
        Compares every base table and string column of the connection's database with one expected collation: a mixed
        collation breaks joins on string columns (MySQL error 1267 "Illegal mix of collations").

          <info>php %command.full_name%</info>                               configured connection and collation
          <info>php %command.full_name% --expected=utf8mb4_unicode_ci</info>
          <info>php %command.full_name% --connection=legacy --ignore-table='archive_*'</info>

        Expected collation: --expected, else nowo_migrations_kit.collation_check.expected_collation, else the
        connection's doctrine default_table_options.collation. Tables in ignore_tables (or --ignore-table) may differ,
        they are not required to; they are counted as "still to convert". Exit 0 when everything matches or the platform
        has no collation check (SQLite), 1 on any other difference, 2 when no expected collation is known or the database
        cannot be read.
        HELP,
)]
final class CheckCollationsCommand extends Command
{
    public const EXIT_MISCONFIGURED = 2;

    public function __construct(
        private readonly ConnectionRegistry $connections,
        private readonly CollationChecker $checker,
        private readonly ?string $defaultConnection = null,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Doctrine connection name (default: collation_check.connection, else nowo_migrations_kit.connection)')
            ->addOption('expected', null, InputOption::VALUE_REQUIRED, 'Expected collation for this run (e.g. utf8mb4_unicode_ci)')
            ->addOption('ignore-table', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Extra table name or fnmatch() pattern allowed to differ (repeatable)');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption('connection');
        $name = is_string($name) && $name !== '' ? $name : $this->defaultConnection;

        $expected = $input->getOption('expected');
        $ignore   = $input->getOption('ignore-table');

        try {
            $connection = $this->connections->getConnection($name);
            if (!$connection instanceof Connection) {
                throw new InvalidArgumentException(sprintf('Connection "%s" is not a DBAL connection.', (string) $name));
            }
            $report = $this->checker->check(
                $connection,
                is_string($expected) && $expected !== '' ? $expected : null,
                is_array($ignore) ? array_values(array_map(strval(...), $ignore)) : [],
            );
        } catch (Throwable $e) {
            $output->writeln('<error>' . OutputFormatter::escape($e->getMessage()) . '</error>');

            return self::EXIT_MISCONFIGURED;
        }

        if (!$report->supported) {
            $output->writeln(sprintf('<comment>SKIP: no collation check for %s (MySQL / MariaDB and PostgreSQL only).</comment>', OutputFormatter::escape($report->platform)));

            return Command::SUCCESS;
        }

        $failures = $report->failures();
        $ignored  = $report->ignored();
        foreach ($failures as $mismatch) {
            $output->writeln('  ' . OutputFormatter::escape($mismatch->describe()));
        }
        if ($output->isVerbose()) {
            foreach ($ignored as $mismatch) {
                $output->writeln('  <comment>ignored</comment> ' . OutputFormatter::escape($mismatch->describe()));
            }
        }

        if ($failures !== []) {
            $output->writeln(sprintf('<error>FAIL: %d object(s) not in %s on %s.</error>', count($failures), OutputFormatter::escape($report->expectedCollation), $report->platform));
            $output->writeln(sprintf(
                'Create new tables with the expected collation (doctrine default_table_options, or DEFAULT CHARACTER SET %s COLLATE %s) or convert them in a migration.',
                OutputFormatter::escape($report->expectedCharset ?? '…'),
                OutputFormatter::escape($report->expectedCollation),
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>OK: collations (%s on %s; %d table(s), %d column(s) checked; %d ignored object(s) still to convert).</info>',
            OutputFormatter::escape($report->expectedCollation),
            $report->platform,
            $report->tablesChecked,
            $report->columnsChecked,
            count($ignored),
        ));

        return Command::SUCCESS;
    }
}
