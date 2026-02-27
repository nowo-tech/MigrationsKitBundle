<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use RuntimeException;

/**
 * Validation: Version20250223100004 — kit_example was dropped.
 */
final class Version20250223100004_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: ' . KitExample::TABLE_NAME . ' dropped (Version20250223100004)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if ($checker->tableExists(KitExample::TABLE_NAME)) {
            throw new RuntimeException('Validation failed: table ' . KitExample::TABLE_NAME . ' should have been dropped.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
