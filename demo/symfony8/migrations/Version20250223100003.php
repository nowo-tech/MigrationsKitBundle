<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use App\Entity\KitUser;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Add foreign key kit_item.user_id -> kit_user.id (phase 2: FK only).
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 4: add foreign keys. Only MDK::FOREIGN_KEYS in the definition (no new
 *   tables/columns). Checks: local table/columns exist, referenced table/columns exist, FK not already present.
 * - MDK::FOREIGN_KEYS — columns, foreign_table, foreign_columns, onDelete (optional name, onUpdate).
 * - Schema comparator used to emit ALTER TABLE ADD CONSTRAINT (DBAL 3/4 compatible via schemaDiffToSql).
 */
final class Version20250223100003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add foreign key ' . KitItem::TABLE_NAME . '.user_id -> ' . KitUser::TABLE_NAME . '.id';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitItem::TABLE_NAME => [
                    MDK::FOREIGN_KEYS => [
                        [
                            'columns'         => ['user_id'],
                            'foreign_table'   => KitUser::TABLE_NAME,
                            'foreign_columns' => ['id'],
                            'onDelete'        => MDK::ON_DELETE_SET_NULL,
                        ],
                    ],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform->getName() !== 'sqlite') {
            $this->addSql('ALTER TABLE ' . KitItem::TABLE_NAME . ' DROP FOREIGN KEY fk_kit_item_user_id');
        }
        // SQLite: dropping a FK requires recreating the table; leave down() as no-op for SQLite
    }
}
