<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Demo: SchemaChecker only (table/column checks).
 * Creates app_settings table and adds created_at column if they do not exist.
 */
final class Version20250219000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: SchemaChecker only - app_settings table + created_at column';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        $isSqlite = str_contains(strtolower($this->connection->getDatabasePlatform()->getName()), 'sqlite');

        if (!$checker->tableExists('demo_kit_app_settings')) {
            if ($isSqlite) {
                $this->addSql('CREATE TABLE demo_kit_app_settings (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, key_name VARCHAR(64) NOT NULL, value CLOB DEFAULT NULL)');
            } else {
                $this->addSql('CREATE TABLE demo_kit_app_settings (id INT AUTO_INCREMENT NOT NULL, key_name VARCHAR(64) NOT NULL, value LONGTEXT DEFAULT NULL, PRIMARY KEY(id), UNIQUE INDEX UNIQ_demo_kit_key (key_name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            }
        }

        if ($checker->tableExists('demo_kit_app_settings') && !$checker->columnExists('demo_kit_app_settings', 'created_at')) {
            $this->addSql($isSqlite
                ? 'ALTER TABLE demo_kit_app_settings ADD COLUMN created_at DATETIME DEFAULT NULL'
                : 'ALTER TABLE demo_kit_app_settings ADD created_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS demo_kit_app_settings');
    }
}
