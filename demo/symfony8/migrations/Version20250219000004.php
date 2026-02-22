<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use Nowo\MigrationsKitBundle\Schema\SchemaLimitChecker;
use Nowo\MigrationsKitBundle\Schema\SchemaSync;

/**
 * Demo: declarative schema (SchemaSync).
 *
 * - Desired schema in one array: sync creates/alters/drops to match (DBAL 3+).
 * - To DROP: omit from definition. Columns/indexes not in the definition are dropped when syncing.
 *   To drop TABLES that exist in DB but not in definition, use: $sync->sync(..., ['drop_tables' => true]).
 * - SchemaLimitChecker: warns if MySQL limits are exceeded (max columns, row size, index key length).
 */
final class Version20250219000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: SchemaSync - declarative schema, drop options, limit checks';
    }

    public function up(Schema $schema): void
    {
        $checker      = new SchemaChecker($this->connection);
        $parser       = new SchemaDefinitionParser();
        $sync         = new SchemaSync($this->connection, $parser, $checker);
        $limitChecker = new SchemaLimitChecker();

        $definition = [
            MDK::TABLES => [
                'demo_kit_product' => [
                    MDK::COLUMNS => [
                        'id'         => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        'name'       => ['type' => 'string', 'length' => 255, 'notnull' => true],
                        'price'      => ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'notnull' => true],
                        'created_at' => ['type' => 'datetime_immutable', 'notnull' => false],
                    ],
                    MDK::PRIMARY_KEY => ['id'],
                    MDK::INDEXES     => [
                        'idx_demo_kit_product_name' => [MDK::COLUMNS => ['name']],
                    ],
                ],
            ],
        ];

        // Warn if definition exceeds MySQL limits (columns, row size, index length)
        $platform = $this->connection->getDatabasePlatform()->getName();
        $limitChecker->warnIfOverLimits($definition, $platform);

        // Sync: creates/alters tables and columns. Omitting a column/index from definition = drop it.
        $addSql = function (string $sql): void {
            $this->addSql($sql);
        };
        $sync->sync($addSql, $definition);

        // Optional: drop tables that exist in DB but are not in $definition:
        // $sync->sync($addSql, $definition, ['drop_tables' => true]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS demo_kit_product');
    }
}
