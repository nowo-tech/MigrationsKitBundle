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
 * Drop index and foreign key on kit_item (user_id) using DROP_INDEXES and DROP_FOREIGN_KEYS.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 1b: drop FKs by name (MDK::DROP_FOREIGN_KEYS).
 * - CreateTablesService::apply() — Phase 1c: drop indexes by name (MDK::DROP_INDEXES).
 * - Enables Version20250223100006 to drop kit_user without Phase 1 (FK already removed here).
 */
final class Version20250223100005 extends AbstractMigration
{
    /** Index on kit_item.user_id created in Version20250223100003 (Doctrine-generated name). */
    private const IDX_KIT_ITEM_USER_ID = 'IDX_E222877DA76ED395';

    private const FK_KIT_ITEM_USER_ID = 'fk_kit_item_user_id';

    public function getDescription(): string
    {
        return 'Drop index and FK on ' . KitItem::TABLE_NAME . ' (user_id) — DROP_INDEXES, DROP_FOREIGN_KEYS';
    }

    public function up(Schema $schema): void
    {
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition = [
            MDK::TABLES => [
                KitItem::TABLE_NAME => [
                    MDK::DROP_INDEXES => [self::IDX_KIT_ITEM_USER_ID],
                    MDK::DROP_FOREIGN_KEYS => [self::FK_KIT_ITEM_USER_ID],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- Restore by re-running migration Version20250223100003');
    }
}
