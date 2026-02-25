<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Add primary key on kit_pk_demo (define new PRIMARY_KEY on existing table).
 *
 * Runs after Version20250223100012 (PK was dropped). This migration adds a new PK
 * on code via Phase 3 — "add or change primary key when table exists".
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3: add primary key on existing table (MDK::PRIMARY_KEY).
 */
final class Version20250223100013 extends AbstractMigration
{
    private const TABLE_NAME = 'kit_pk_demo';

    public function getDescription(): string
    {
        return 'Add primary key on ' . self::TABLE_NAME . ' (code) — PRIMARY_KEY (existing table)';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                self::TABLE_NAME => [
                    MDK::PRIMARY_KEY => [['columns' => ['code']]],
                ],
            ],
        ];
        foreach ($service->apply($introspected, $definition) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('-- Reverse: run Version20250223100012 to drop PK again');
    }
}
