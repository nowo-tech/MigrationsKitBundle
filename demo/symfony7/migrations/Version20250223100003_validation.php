<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use RuntimeException;

/**
 * Validation: Version20250223100003 — FK fk_kit_item_user_id on kit_item (skip if dropped in 00005).
 */
final class Version20250223100003_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: FK fk_kit_item_user_id on ' . KitItem::TABLE_NAME . ' (Version20250223100003; skip if dropped in 00005)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->foreignKeyExists(KitItem::TABLE_NAME, 'fk_kit_item_user_id')) {
            return;
        }
        // FK exists — migration 00003 result still present (00005 not run or SQLite)
        // Validate that onDelete SET NULL was applied (MDK foreign_keys options must appear in DB)
        $table = $this->connection->createSchemaManager()->introspectTable(KitItem::TABLE_NAME);
        $fk    = $table->getForeignKey('fk_kit_item_user_id');
        if (method_exists($fk, 'getOptions')) {
            $opts = $fk->getOptions();
            if (($opts['onDelete'] ?? '') !== 'SET NULL') {
                throw new RuntimeException('Validation failed: FK fk_kit_item_user_id on ' . KitItem::TABLE_NAME . ' must have onDelete SET NULL (from MDK definition).');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
