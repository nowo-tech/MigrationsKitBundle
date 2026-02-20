<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;

/**
 * Demo: listTableColumns to add only missing columns.
 * Adds phone and notes to demo_kit_users if they do not exist (table created in Version20250219000000).
 */
final class Version20250219000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: listTableColumns - add missing columns to demo_kit_users';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists('demo_kit_users')) {
            return;
        }

        $columns = $checker->listTableColumns('demo_kit_users');
        $isSqlite = str_contains(strtolower($this->connection->getDatabasePlatform()->getName()), 'sqlite');

        if (!in_array('phone', $columns, true)) {
            $this->addSql($isSqlite
                ? 'ALTER TABLE demo_kit_users ADD COLUMN phone VARCHAR(32) DEFAULT NULL'
                : 'ALTER TABLE demo_kit_users ADD phone VARCHAR(32) DEFAULT NULL');
        }
        if (!in_array('notes', $columns, true)) {
            $this->addSql($isSqlite
                ? 'ALTER TABLE demo_kit_users ADD COLUMN notes CLOB DEFAULT NULL'
                : 'ALTER TABLE demo_kit_users ADD notes LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists('demo_kit_users')) {
            return;
        }
        if ($checker->columnExists('demo_kit_users', 'phone')) {
            $this->addSql('ALTER TABLE demo_kit_users DROP COLUMN phone');
        }
        if ($checker->columnExists('demo_kit_users', 'notes')) {
            $this->addSql('ALTER TABLE demo_kit_users DROP COLUMN notes');
        }
    }
}
