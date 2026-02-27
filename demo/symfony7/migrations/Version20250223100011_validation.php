<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use RuntimeException;

/**
 * Validation: Version20250223100011 — kit_pk_demo created with id, code and PK.
 */
final class Version20250223100011_validation extends AbstractMigration
{
    private const TABLE_NAME = 'kit_pk_demo';

    public function getDescription(): string
    {
        return 'Validation: table ' . self::TABLE_NAME . ' created (Version20250223100011)';
    }

    public function up(Schema $schema): void
    {
        $checker = new SchemaChecker($this->connection);
        if (!$checker->tableExists(self::TABLE_NAME)) {
            throw new RuntimeException('Validation failed: table ' . self::TABLE_NAME . ' was not created.');
        }
        if (!$checker->columnExists(self::TABLE_NAME, 'id') || !$checker->columnExists(self::TABLE_NAME, 'code')) {
            throw new RuntimeException('Validation failed: table ' . self::TABLE_NAME . ' must have columns id and code.');
        }
        if (!$checker->hasPrimaryKey(self::TABLE_NAME)) {
            throw new RuntimeException('Validation failed: table ' . self::TABLE_NAME . ' has no primary key.');
        }
    }

    public function down(Schema $schema): void
    {
        // No-op: validation only
    }
}
