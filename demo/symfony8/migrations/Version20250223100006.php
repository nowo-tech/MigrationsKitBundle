<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use App\Entity\KitUser;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Drop table kit_user.
 *
 * After Version20250223100005, the FK from kit_item to kit_user is already dropped on MySQL/Postgres.
 * On SQLite the bundle does not emit DROP FOREIGN KEY (Phase 1b returns null), so we recreate kit_item
 * without the FK before DROP TABLE kit_user.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 1 (only on SQLite: no FKs to drop if 00005 ran on SQLite we still have FK).
 * - CreateTablesService::apply() — Phase 2: DROP TABLE.
 */
final class Version20250223100006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop table ' . KitUser::TABLE_NAME;
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        // SQLite: 00005 does not drop the FK (getDropForeignKeySQL returns null). Recreate kit_item without FK.
        if ($platform instanceof SqlitePlatform && $schema->hasTable(KitItem::TABLE_NAME)) {
            $this->addSql('CREATE TABLE __temp_' . KitItem::TABLE_NAME . ' AS SELECT id, user_id FROM ' . KitItem::TABLE_NAME);
            $this->addSql('DROP TABLE ' . KitItem::TABLE_NAME);
            $this->addSql('CREATE TABLE ' . KitItem::TABLE_NAME . ' (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL)');
            $this->addSql('INSERT INTO ' . KitItem::TABLE_NAME . ' (id, user_id) SELECT id, user_id FROM __temp_' . KitItem::TABLE_NAME);
            $this->addSql('DROP TABLE __temp_' . KitItem::TABLE_NAME);
        }

        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::DROP_TABLES => [KitUser::TABLE_NAME],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- Restore by re-running migrations Version20250223100002, 00003, 00005');
    }
}
