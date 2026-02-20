<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionRunner;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Schema\StandardColumns;

/**
 * Demo: add standard audit columns (created_at, updated_at, created_by, updated_by + indexes)
 * to an existing table using StandardColumns::auditColumnSteps() and auditIndexSteps().
 */
final class Version20250219000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demo: StandardColumns - add audit fields to demo_kit_users';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        $runner = new MigrationDefinitionRunner($checker);
        $driver = $this->connection->getDatabasePlatform()->getName();
        $isSqlite = str_contains(strtolower($driver), 'sqlite');

        $addSql = function (string $sql): void {
            $this->addSql($sql);
        };

        $runner->run([
            'columns' => StandardColumns::auditColumnSteps('demo_kit_users', $isSqlite),
        ], $addSql);

        foreach (StandardColumns::auditIndexSteps('demo_kit_users', $isSqlite) as $step) {
            $runner->ensureIndex($step['table'], $step['index'], $step['add_sql'], $addSql);
        }
    }

    public function down(Schema $schema): void
    {
        $isSqlite = str_contains(strtolower($this->connection->getDatabasePlatform()->getName()), 'sqlite');

        if ($isSqlite) {
            $this->addSql('DROP INDEX IF EXISTS idx_updated_by');
            $this->addSql('DROP INDEX IF EXISTS idx_created_by');
            $this->addSql('ALTER TABLE demo_kit_users DROP COLUMN updated_by');
            $this->addSql('ALTER TABLE demo_kit_users DROP COLUMN created_by');
            $this->addSql('ALTER TABLE demo_kit_users DROP COLUMN updated_at');
            $this->addSql('ALTER TABLE demo_kit_users DROP COLUMN created_at');
        } else {
            $this->addSql('DROP INDEX idx_updated_by ON demo_kit_users');
            $this->addSql('DROP INDEX idx_created_by ON demo_kit_users');
            $this->addSql('ALTER TABLE demo_kit_users DROP updated_by');
            $this->addSql('ALTER TABLE demo_kit_users DROP created_by');
            $this->addSql('ALTER TABLE demo_kit_users DROP updated_at');
            $this->addSql('ALTER TABLE demo_kit_users DROP created_at');
        }
    }
}
