<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use App\Entity\KitUser;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100002 — kit_user exists and kit_item has user_id (skip if dropped later).
 */
final class Version20250223100002_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: ' . KitUser::TABLE_NAME . ' and ' . KitItem::TABLE_NAME . '.user_id (Version20250223100002; skip if dropped later)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists(KitUser::TABLE_NAME)) {
            return;
        }
        if (!$checker->columnExists(KitItem::TABLE_NAME, 'user_id')) {
            throw new \RuntimeException('Validation failed: column user_id was not added to ' . KitItem::TABLE_NAME . '.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
