<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Validation: Version20250223100010 — index on col_title and unique on col_guid in kit_example.
 * Skip if table was dropped in 00004 or table has no col_title/col_guid (00010 was no-op).
 */
final class Version20250223100010_validation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Validation: index on col_title and unique on col_guid (Version20250223100010; skip if table/columns missing)';
    }

    public function up(Schema $schema): void
    {
        $checker = new \Nowo\MigrationsKitBundle\Migration\SchemaChecker($this->connection);
        if (!$checker->tableExists(KitExample::TABLE_NAME)) {
            return;
        }
        if (!$checker->columnExists(KitExample::TABLE_NAME, 'col_title') || !$checker->columnExists(KitExample::TABLE_NAME, 'col_guid')) {
            return;
        }
        $table   = $this->connection->createSchemaManager()->introspectTable(KitExample::TABLE_NAME);
        $indexes = $table->getIndexes();
        $hasTitleIndex = false;
        $hasGuidUnique = false;
        foreach ($indexes as $index) {
            $cols = method_exists($index, 'getIndexedColumns') ? $index->getIndexedColumns() : $index->getColumns();
            if ($cols === ['col_title']) {
                $hasTitleIndex = true;
            }
            if ($cols === ['col_guid'] && $index->isUnique()) {
                $hasGuidUnique = true;
            }
        }
        if (!$hasTitleIndex) {
            throw new \RuntimeException('Validation failed: no index on col_title was found on ' . KitExample::TABLE_NAME . '.');
        }
        if (!$hasGuidUnique) {
            throw new \RuntimeException('Validation failed: no unique index on col_guid was found on ' . KitExample::TABLE_NAME . '.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
