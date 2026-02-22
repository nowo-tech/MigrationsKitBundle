<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Demo migration using MigrationsKitBundle (Symfony 6 / doctrine/migrations 3.x).
 */
final class Version20250219000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: create demo_kit_users via MigrationsKitBundle (table + column from array)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        $runner  = new MigrationDefinitionRunner($checker);

        $driver   = $this->connection->getDatabasePlatform()->getName();
        $isSqlite = str_contains(strtolower($driver), 'sqlite');

        $runner->run(
            [
                MDK::TABLES => [
                    'demo_kit_users' => [
                        'create_sql' => $isSqlite
                            ? 'CREATE TABLE demo_kit_users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)'
                            : 'CREATE TABLE demo_kit_users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB',
                    ],
                ],
                MDK::COLUMNS => [
                    [
                        'table'   => 'demo_kit_users',
                        'column'  => 'email',
                        'add_sql' => $isSqlite
                            ? 'ALTER TABLE demo_kit_users ADD COLUMN email VARCHAR(180) DEFAULT NULL'
                            : 'ALTER TABLE demo_kit_users ADD email VARCHAR(180) DEFAULT NULL',
                    ],
                ],
            ],
            function (string $sql): void {
                $this->addSql($sql);
            },
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS demo_kit_users');
    }
}
