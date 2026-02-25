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
 * Add index on col_title and unique on col_guid in kit_example (MDK::INDEXES).
 *
 * Runs after Version20250223100008 (col_string renamed to col_title). Phase 4a creates
 * indexes/unique when table and columns exist and index name not already present.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 4a: create index and unique via INDEXES.
 */
final class Version20250223100010 extends AbstractMigration
{
    private const IDX_KIT_EXAMPLE_COL_TITLE = 'idx_kit_example_col_title';
    private const UNIQ_KIT_EXAMPLE_COL_GUID = 'uniq_kit_example_col_guid';

    public function getDescription(): string
    {
        return 'Add index and unique on ' . KitExample::TABLE_NAME . ' — INDEXES';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitExample::TABLE_NAME => [
                    MDK::INDEXES => [
                        ['columns' => ['col_title'], 'name' => self::IDX_KIT_EXAMPLE_COL_TITLE],
                        ['columns' => ['col_guid'], 'unique' => true, 'name' => self::UNIQ_KIT_EXAMPLE_COL_GUID],
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
        $this->addSql('-- Reverse: drop index and unique (use DROP_INDEXES in a separate migration or manual DROP INDEX)');
    }
}
