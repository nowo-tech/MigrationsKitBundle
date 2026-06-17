<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Nowo\MigrationsKitBundle\Schema\TableSchemaHelper;
use PHPUnit\Framework\TestCase;

class TableSchemaHelperTest extends TestCase
{
    public function testSetPrimaryKeyAddsConstraintOnSupportedPlatforms(): void
    {
        $table = new Table('example');
        $table->addColumn('id', 'integer', ['notnull' => true]);

        TableSchemaHelper::setPrimaryKey($table, ['id'], 'pk_example');

        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['id'], $table->getPrimaryKey()->getColumns());
    }

    public function testCreateSchemaComparatorUsesDbalComparator(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $schemaManager = $connection->createSchemaManager();
        $comparator    = TableSchemaHelper::createSchemaComparator($schemaManager);

        self::assertInstanceOf(Comparator::class, $comparator);
    }
}
