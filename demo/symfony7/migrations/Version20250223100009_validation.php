<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100009 — col_string_nullable modified (skip if kit_example dropped in 00004).
 */
final class Version20250223100009_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: col_string_nullable on ' . KitExample::TABLE_NAME . ' (Version20250223100009; skip if table dropped)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists(KitExample::TABLE_NAME)) {
            return;
        }
        if (!$checker->columnExists(KitExample::TABLE_NAME, 'col_string_nullable')) {
            throw new \RuntimeException('Validation failed: column col_string_nullable should exist on ' . KitExample::TABLE_NAME . '.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
