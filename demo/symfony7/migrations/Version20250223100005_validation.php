<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use RuntimeException;

/**
 * Validation: Version20250223100005 — FK and index on kit_item.user_id dropped (skip on SQLite).
 */
final class Version20250223100005_validation extends AbstractMigration
{
    private const IDX_KIT_ITEM_USER_ID = 'IDX_E222877DA76ED395';
    private const FK_KIT_ITEM_USER_ID  = 'fk_kit_item_user_id';

    public function getDescription(): string
    {
        return 'Validation: index and FK on ' . KitItem::TABLE_NAME . ' dropped (Version20250223100005; skip on SQLite)';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }
        $checker = new SchemaChecker($this->connection);
        if ($checker->foreignKeyExists(KitItem::TABLE_NAME, self::FK_KIT_ITEM_USER_ID)) {
            throw new RuntimeException('Validation failed: foreign key ' . self::FK_KIT_ITEM_USER_ID . ' should have been dropped.');
        }
        if ($checker->indexExists(KitItem::TABLE_NAME, self::IDX_KIT_ITEM_USER_ID)) {
            throw new RuntimeException('Validation failed: index ' . self::IDX_KIT_ITEM_USER_ID . ' should have been dropped.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
