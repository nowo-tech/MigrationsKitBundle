<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitUser;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Validation: Version20250223100006 — kit_user was dropped.
 */
final class Version20250223100006_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: ' . KitUser::TABLE_NAME . ' dropped (Version20250223100006)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if ($checker->tableExists(KitUser::TABLE_NAME)) {
            throw new \RuntimeException('Validation failed: table ' . KitUser::TABLE_NAME . ' should have been dropped.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
