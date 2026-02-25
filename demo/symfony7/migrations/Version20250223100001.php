<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\KitExample;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use DoctrineMigrations\FieldDictionary\AuditFields;
use Nowo\MigrationsKitBundle\FieldDictionary\IdField;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;

/**
 * Create table kit_example with all supported column types and options (demo of full MDK variants).
 *
 * Bundle under test:
 * - CreateTablesService::apply() — Phase 3: CREATE TABLE with full column set.
 * - MDK::COLUMNS — multiple DBAL types (smallint, bigint, boolean, decimal, float, string, text, ascii_string,
 *   datetime, datetime_immutable, date, time, json, blob, guid) and options (length, precision, scale, notnull,
 *   default, comment).
 * - IdField (bundle) + AuditFields::timestampColumns() (demo FieldDictionary) — created_at, updated_at.
 * - MDK::PRIMARY_KEY via IdField::primaryKey().
 */
final class Version20250223100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table ' . KitExample::TABLE_NAME . ' (all column types and options)';
    }

    public function up(Schema $schema): void
    {
        $service      = new CreateTablesService($this->connection, new SchemaDefinitionParser());
        $introspected = $this->connection->createSchemaManager()->introspectSchema();
        $definition   = [
            MDK::TABLES => [
                KitExample::TABLE_NAME => [
                    MDK::COLUMNS => [
                        IdField::column(),
                        ['name' => 'col_smallint', 'type' => 'smallint', 'notnull' => true, 'default' => 0],
                        ['name' => 'col_bigint', 'type' => 'bigint', 'notnull' => false],
                        ['name' => 'col_boolean', 'type' => 'boolean', 'notnull' => true, 'default' => true],
                        ['name' => 'col_decimal', 'type' => 'decimal', 'precision' => 10, 'scale' => 2, 'notnull' => true, 'default' => '0.00'],
                        ['name' => 'col_float', 'type' => 'float', 'notnull' => false],
                        ['name' => 'col_string', 'type' => 'string', 'length' => 255, 'notnull' => true],
                        ['name' => 'col_string_nullable', 'type' => 'string', 'length' => 100, 'notnull' => false],
                        ['name' => 'col_text', 'type' => 'text', 'notnull' => false],
                        ['name' => 'col_ascii', 'type' => 'ascii_string', 'length' => 64, 'notnull' => false],
                        ['name' => 'col_datetime', 'type' => 'datetime', 'notnull' => false],
                        ['name' => 'col_datetime_immutable', 'type' => 'datetime_immutable', 'notnull' => false],
                        ['name' => 'col_date', 'type' => 'date', 'notnull' => false],
                        ['name' => 'col_time', 'type' => 'time', 'notnull' => false],
                        ['name' => 'col_json', 'type' => 'json', 'notnull' => false],
                        ['name' => 'col_blob', 'type' => 'blob', 'notnull' => false],
                        ['name' => 'col_guid', 'type' => 'guid', 'notnull' => false],
                        ['name' => 'col_comment', 'type' => 'string', 'length' => 50, 'notnull' => false, 'comment' => 'Example comment'],
                        ...AuditFields::timestampColumns(),
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
        $this->addSql('DROP TABLE IF EXISTS ' . KitExample::TABLE_NAME);
    }
}
