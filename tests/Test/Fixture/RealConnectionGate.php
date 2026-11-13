<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Doctrine\DBAL\Connection;
use Nowo\MigrationsKitBundle\Test\ExactKeyColumnsTestCase;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumns;

/**
 * Extends the shipped gate without faking anything, so the real DSN handling is what runs: no environment variable
 * means no database and a clean skip, a usable DSN means a connection, an unusable one means null.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RealConnectionGate extends ExactKeyColumnsTestCase
{
    /** Environment variable to read; null keeps the shipped default. */
    public ?string $variable = null;

    /**
     * {@inheritdoc}
     */
    protected function exactKeyColumnProviders(): array
    {
        return [new ExactKeyColumns(new ExactKeyColumn('t', 'c', 'o'))];
    }

    public function variableName(): string
    {
        return $this->databaseUrlVariable();
    }

    public function connect(): ?Connection
    {
        return $this->createTestConnection();
    }

    /**
     * {@inheritdoc}
     */
    protected function databaseUrlVariable(): string
    {
        return $this->variable ?? parent::databaseUrlVariable();
    }
}
