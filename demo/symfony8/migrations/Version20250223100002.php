<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use App\Entity\KitUser;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Create table kit_user and add user_id column to kit_item (tables and columns only; no foreign key yet).
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3: mix of (1) new table creation (kit_user) and (2) ALTER TABLE ADD COLUMN
 *   for existing table (kit_item.user_id). Uses schema comparator for add-column SQL (DBAL 3/4 compatible).
 * - IdField + AuditFields::timestampColumns() on kit_user — new table with id, name, created_at, updated_at.
 * - MDK::TABLES with multiple tables in one definition — one CREATE TABLE, one ALTER TABLE in a single apply().
 */
final class Version20250223100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table ' . KitUser::TABLE_NAME . ' and add user_id to ' . KitItem::TABLE_NAME;
    }

    public function up(Schema $schema): void
    {
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition = [
            MDK::TABLES => [
                KitUser::TABLE_NAME => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'name', 'type' => 'string', 'length' => 180, 'notnull' => true],
                        ...AuditFields::timestampColumns(),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                ],
                KitItem::TABLE_NAME => [
                    MDK::COLUMNS => [
                        ['name' => 'user_id', 'type' => 'integer', 'notnull' => false],
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
        $this->addSql('ALTER TABLE ' . KitItem::TABLE_NAME . ' DROP COLUMN user_id');
        $this->addSql('DROP TABLE IF EXISTS ' . KitUser::TABLE_NAME);
    }
}
