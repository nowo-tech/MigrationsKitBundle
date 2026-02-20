<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Demo: ensureTable, ensureColumn, ensureIndex (runner direct methods).
 * Creates audit_log table and adds column and index if they do not exist.
 */
final class Version20250219000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: ensureTable / ensureColumn / ensureIndex - audit_log';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        $runner = new MigrationDefinitionRunner($checker);
        $isSqlite = str_contains(strtolower($this->connection->getDatabasePlatform()->getName()), 'sqlite');

        $createTable = $isSqlite
            ? 'CREATE TABLE demo_kit_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, action VARCHAR(64) NOT NULL)'
            : 'CREATE TABLE demo_kit_audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';

        $addSql = function (string $sql): void {
            $this->addSql($sql);
        };
        $runner->ensureTable('demo_kit_audit_log', $createTable, $addSql);
        $runner->ensureColumn('demo_kit_audit_log', 'created_at', $isSqlite
            ? 'ALTER TABLE demo_kit_audit_log ADD COLUMN created_at DATETIME NOT NULL'
            : 'ALTER TABLE demo_kit_audit_log ADD created_at DATETIME NOT NULL', $addSql);
        $runner->ensureIndex('demo_kit_audit_log', 'idx_demo_kit_audit_action', $isSqlite
            ? 'CREATE INDEX idx_demo_kit_audit_action ON demo_kit_audit_log (action)'
            : 'CREATE INDEX idx_demo_kit_audit_action ON demo_kit_audit_log (action)', $addSql);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS demo_kit_audit_log');
    }
}
