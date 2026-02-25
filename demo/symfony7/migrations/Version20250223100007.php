<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Drop column user_id from kit_item using DROP_COLUMNS.
 *
 * Runs after Version20250223100006 (kit_user already dropped). kit_item still has user_id;
 * this migration removes it via Phase 2a.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 2a: drop columns by name (MDK::DROP_COLUMNS).
 */
final class Version20250223100007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop column user_id from ' . KitItem::TABLE_NAME . ' — DROP_COLUMNS';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitItem::TABLE_NAME => [
                    MDK::DROP_COLUMNS => ['user_id'],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- Restore by re-running migrations Version20250223100002 and 00003');
    }
}
