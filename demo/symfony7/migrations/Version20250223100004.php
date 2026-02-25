<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Drop table kit_example — simple drop (no dependencies).
 *
 * No table has an FK pointing to kit_example, so the bundle only runs Phase 2: DROP TABLE
 * (Phase 1 finds no FKs that reference this table).
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 1: drop FKs that reference tables in DROP_TABLES (here: none).
 * - CreateTablesService::apply() — Phase 2: drop tables in MDK::DROP_TABLES; only if table exists.
 * - MDK::DROP_TABLES — list of table names to drop; checks before dropping.
 */
final class Version20250223100004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop table ' . KitExample::TABLE_NAME . ' (simple drop, no dependencies)';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::DROP_TABLES => [KitExample::TABLE_NAME],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        // To restore kit_example, re-run the migration that created it (Version20250223100001).
        $this->addSql('-- Restore by re-running migration Version20250223100001');
    }
}
