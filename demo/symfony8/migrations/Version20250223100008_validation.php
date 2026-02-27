<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100008 — col_string renamed to col_title in kit_example (skip if table dropped in 00004).
 */
final class Version20250223100008_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: col_string -> col_title on ' . KitExample::TABLE_NAME . ' (Version20250223100008; skip if table dropped)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists(KitExample::TABLE_NAME)) {
            return;
        }
        if (!$checker->columnExists(KitExample::TABLE_NAME, 'col_title')) {
            throw new \RuntimeException('Validation failed: column col_title was not created on ' . KitExample::TABLE_NAME . '.');
        }
        if ($checker->columnExists(KitExample::TABLE_NAME, 'col_string')) {
            throw new \RuntimeException('Validation failed: column col_string should have been renamed to col_title on ' . KitExample::TABLE_NAME . '.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
