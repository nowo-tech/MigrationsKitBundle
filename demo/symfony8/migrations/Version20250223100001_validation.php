<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100001 — kit_example exists with id and PK (skip if dropped later in 00004).
 */
final class Version20250223100001_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: ' . KitExample::TABLE_NAME . ' created (Version20250223100001; skip if dropped in 00004)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists(KitExample::TABLE_NAME)) {
            return;
        }
        if (!$checker->columnExists(KitExample::TABLE_NAME, 'id')) {
            throw new \RuntimeException('Validation failed: column id does not exist on ' . KitExample::TABLE_NAME . '.');
        }
        if (!$checker->hasPrimaryKey(KitExample::TABLE_NAME)) {
            throw new \RuntimeException('Validation failed: ' . KitExample::TABLE_NAME . ' has no primary key.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
