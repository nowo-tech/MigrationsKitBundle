<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100007 — user_id column dropped from kit_item.
 */
final class Version20250223100007_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: column user_id dropped from ' . KitItem::TABLE_NAME . ' (Version20250223100007)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if ($checker->columnExists(KitItem::TABLE_NAME, 'user_id')) {
            throw new \RuntimeException('Validation failed: column user_id should have been dropped from ' . KitItem::TABLE_NAME . '.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
