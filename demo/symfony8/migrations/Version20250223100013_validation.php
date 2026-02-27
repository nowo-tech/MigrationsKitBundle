<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100013 — kit_pk_demo has primary key on code (skip on SQLite).
 */
final class Version20250223100013_validation extends AbstractMigration
{
    private const TABLE_NAME = 'kit_pk_demo';

    public function getDescription(): string
    {
        return 'Validation: PK on code on ' . self::TABLE_NAME . ' (Version20250223100013; skip on SQLite)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }
        $checker = new SchemaChecker($this->connection);
        if (!$checker->hasPrimaryKey(self::TABLE_NAME)) {
            throw new \RuntimeException('Validation failed: table ' . self::TABLE_NAME . ' should have a primary key on code.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
