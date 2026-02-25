<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Drop primary key on kit_pk_demo using DROP_PRIMARY_KEYS.
 *
 * Runs after Version20250223100011. kit_pk_demo has PK(id) with no AUTO_INCREMENT,
 * so MySQL allows DROP PRIMARY KEY. Version20250223100013 adds a new PK on code.
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 2b: drop primary key (MDK::DROP_PRIMARY_KEYS).
 */
final class Version20250223100012 extends AbstractMigration
{
    private const TABLE_NAME = 'kit_pk_demo';

    public function getDescription(): string
    {
        return 'Drop primary key on ' . self::TABLE_NAME . ' — DROP_PRIMARY_KEYS';
    }

    public function up(Schema $schema): void
    {
        $service = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition = [
            MDK::TABLES => [
                self::TABLE_NAME => [
                    MDK::DROP_PRIMARY_KEYS => [],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- Restore PK by running Version20250223100013');
    }
}
