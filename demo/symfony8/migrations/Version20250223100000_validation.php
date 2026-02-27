<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use RuntimeException;

/**
 * Validation: Version20250223100000 — kit_item exists with id and primary key.
 */
final class Version20250223100000_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: ' . KitItem::TABLE_NAME . ' created with id and PK (Version20250223100000)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists(KitItem::TABLE_NAME)) {
            throw new RuntimeException('Validation failed: table ' . KitItem::TABLE_NAME . ' was not created.');
        }
        if (!$checker->columnExists(KitItem::TABLE_NAME, 'id')) {
            throw new RuntimeException('Validation failed: column id does not exist on ' . KitItem::TABLE_NAME . '.');
        }
        if (!$checker->hasPrimaryKey(KitItem::TABLE_NAME)) {
            throw new RuntimeException('Validation failed: ' . KitItem::TABLE_NAME . ' has no primary key.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
