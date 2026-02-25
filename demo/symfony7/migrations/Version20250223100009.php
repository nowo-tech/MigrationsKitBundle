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
 * Modify column col_string_nullable in kit_example: length 100 → 200 (Phase 3b modify column).
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3b: modify column type/options when definition differs from introspected.
 */
final class Version20250223100009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modify col_string_nullable length to 200 in ' . KitExample::TABLE_NAME;
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitExample::TABLE_NAME => [
                    MDK::COLUMNS => [
                        ['name' => 'col_string_nullable', 'type' => 'string', 'length' => 200, 'notnull' => false],
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
        $this->addSql('-- Reverse: change col_string_nullable length back to 100 (manual or redefine length 100)');
    }
}
