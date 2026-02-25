<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Create table kit_pk_demo for DROP_PRIMARY_KEYS / change PRIMARY_KEY demos.
 *
 * Uses id (INT, no AUTO_INCREMENT) and code (VARCHAR) so we can drop the PK on MySQL:
 * MySQL requires an AUTO_INCREMENT column to be part of a key, so we use a dedicated
 * table without auto_increment for the PK demos in 00012 and 00013.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3: create table (COLUMNS + PRIMARY_KEY, no autoincrement).
 */
final class Version20250223100011 extends AbstractMigration
{
    private const TABLE_NAME = 'kit_pk_demo';

    public function getDescription(): string
    {
        return 'Create table ' . self::TABLE_NAME . ' (for PK drop/change demos)';
    }

    public function up(Schema $schema): void
    {
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition = [
            MDK::TABLES => [
                self::TABLE_NAME => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'notnull' => true],
                        ['name' => 'code', 'type' => 'string', 'length' => 32, 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ' . self::TABLE_NAME);
    }
}
