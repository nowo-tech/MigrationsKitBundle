<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Demo: set/update data via MigrationDefinitionRunner (data steps).
 * Inserts default app settings only when missing; updates a value when row exists.
 */
final class Version20250219000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: data steps - insert/update demo_kit_app_settings (only_if_not_exists / only_if_exists)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        $runner  = new MigrationDefinitionRunner($checker);

        $addSql = function (string $sql, array $params = []): void {
            $this->addSql($sql, $params);
        };

        $runner->run([
            MDK::DATA => [
                [
                    MDK::INSERT => [
                        'table'              => 'demo_kit_app_settings',
                        'row'                => ['key_name' => 'app.version', 'value' => '1.0'],
                        'only_if_not_exists' => ['key_name' => 'app.version'],
                    ],
                ],
                [
                    MDK::INSERT => [
                        'table'              => 'demo_kit_app_settings',
                        'row'                => ['key_name' => 'app.name', 'value' => 'MigrationsKit Demo'],
                        'only_if_not_exists' => ['key_name' => 'app.name'],
                    ],
                ],
                [
                    MDK::UPDATE => [
                        'table'          => 'demo_kit_app_settings',
                        'set'            => ['value' => '1.1'],
                        'where'          => ['key_name' => 'app.version'],
                        'only_if_exists' => true,
                    ],
                ],
            ],
        ], $addSql);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM demo_kit_app_settings WHERE key_name IN ('app.version', 'app.name')");
    }
}
