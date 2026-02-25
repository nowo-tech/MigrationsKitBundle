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
 * Rename column col_string to col_title in kit_example (MDK::RENAME).
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3a: rename column via RENAME key.
 */
final class Version20250223100008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename col_string to col_title in ' . KitExample::TABLE_NAME . ' — RENAME';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitExample::TABLE_NAME => [
                    MDK::COLUMNS => [
                        ['name' => 'col_string', MDK::RENAME => 'col_title'],
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
        $this->addSql('-- Reverse: rename col_title back to col_string (manual or re-run 00008 def with swapped names)');
    }
}
