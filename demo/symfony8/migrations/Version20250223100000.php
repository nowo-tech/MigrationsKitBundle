<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitItem;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Create table kit_item with one simple column (id) using the bundle's CreateTablesService.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3: create table when it does not exist (introspectSchema + MDK definition).
 * - SchemaDefinitionParser::parseTable() — COLUMNS + PRIMARY_KEY.
 * - FieldDictionary\IdField::column() and IdField::primaryKey() — reusable id column and PK from the bundle.
 */
final class Version20250223100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table ' . KitItem::TABLE_NAME . ' with one column (id)';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitItem::TABLE_NAME => [
                    MDK::COLUMNS => [
                        IdField::column(),
                    ],
                    MDK::PRIMARY_KEY => IdField::primaryKey(),
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ' . KitItem::TABLE_NAME);
    }
}
