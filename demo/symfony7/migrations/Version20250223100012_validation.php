<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100012 — primary key dropped on kit_pk_demo (skip on SQLite).
 */
final class Version20250223100012_validation extends AbstractMigration
{
    private const TABLE_NAME = 'kit_pk_demo';

    public function getDescription(): string
    {
        return 'Validation: PK dropped on ' . self::TABLE_NAME . ' (Version20250223100012; skip on SQLite)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }
        $checker = new SchemaChecker($this->connection);
        if ($checker->hasPrimaryKey(self::TABLE_NAME)) {
            throw new \RuntimeException('Validation failed: primary key on ' . self::TABLE_NAME . ' should have been dropped.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
