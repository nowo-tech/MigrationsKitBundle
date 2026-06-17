<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use Nowo\MigrationsKitBundle\Schema\TableSchemaHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

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

    public function testSetPrimaryKeyWithoutConstraintName(): void
    {
        $table = new Table('example');
        $table->addColumn('id', 'integer', ['notnull' => true]);
        $table->addColumn('tenant_id', 'integer', ['notnull' => true]);

        TableSchemaHelper::setPrimaryKey($table, ['id', 'tenant_id']);

        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['id', 'tenant_id'], $table->getPrimaryKey()->getColumns());
    }

    public function testSetPrimaryKeyWithEmptyColumnNamesIsNoOp(): void
    {
        $table = new Table('example');
        $table->addColumn('id', 'integer', ['notnull' => true]);

        TableSchemaHelper::setPrimaryKey($table, []);

        self::assertNull($table->getPrimaryKey());
    }

    public function testSetPrimaryKeyUsesLegacyApiWhenPrimaryKeyConstraintUnavailable(): void
    {
        $table = new Table('legacy');
        $table->addColumn('id', 'integer', ['notnull' => true]);

        LegacyPrimaryKeyTableSchemaHelper::setPrimaryKey($table, ['id'], 'pk_legacy');

        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['id'], $table->getPrimaryKey()->getColumns());
    }

    public function testSetPrimaryKeyUsesLegacyApiWithoutConstraintNameWhenPrimaryKeyConstraintUnavailable(): void
    {
        $table = new Table('legacy');
        $table->addColumn('id', 'integer', ['notnull' => true]);

        LegacyPrimaryKeyTableSchemaHelper::setPrimaryKey($table, ['id']);

        self::assertNotNull($table->getPrimaryKey());
    }

    public function testCreateSchemaComparatorUsesDbalComparator(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $schemaManager = $connection->createSchemaManager();
        $comparator    = TableSchemaHelper::createSchemaComparator($schemaManager);
        $schema        = new Schema();
        $diff          = $comparator->compareSchemas($schema, $schema);

        self::assertTrue($diff->isEmpty());
    }

    public function testCreateSchemaComparatorThrowsWhenSchemaManagerUnsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema manager must provide createComparator().');

        TableSchemaHelper::createSchemaComparator(new stdClass());
    }

    public function testCreateSchemaComparatorUsesComparatorConfigWhenSupported(): void
    {
        if (!class_exists(ComparatorConfig::class)) {
            self::markTestSkipped('ComparatorConfig is not available on this DBAL version.');
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $innerComparator = $connection->createSchemaManager()->createComparator();
        $manager         = new ComparatorConfigCapturingSchemaManager($innerComparator);

        $comparator = TableSchemaHelper::createSchemaComparator($manager);

        self::assertTrue($manager->receivedConfig);
        self::assertTrue($comparator->compareSchemas(new Schema(), new Schema())->isEmpty());
    }

    public function testCreateSchemaComparatorFallsBackWhenConfigComparatorFails(): void
    {
        if (!class_exists(ComparatorConfig::class)) {
            self::markTestSkipped('ComparatorConfig is not available on this DBAL version.');
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $innerComparator = $connection->createSchemaManager()->createComparator();
        $manager         = new ThrowingConfigComparatorSchemaManager($innerComparator);

        $comparator = TableSchemaHelper::createSchemaComparator($manager);

        self::assertTrue($manager->fallbackCalled);
        self::assertTrue($comparator->compareSchemas(new Schema(), new Schema())->isEmpty());
    }
}

/**
 * Schema manager stub that accepts ComparatorConfig (DBAL 4 style).
 *
 * @internal
 */
final class ComparatorConfigCapturingSchemaManager
{
    public bool $receivedConfig = false;

    public function __construct(private readonly Comparator $inner)
    {
    }

    public function createComparator(ComparatorConfig $config): Comparator
    {
        $this->receivedConfig = true;

        return $this->inner;
    }
}

/**
 * Schema manager stub: config comparator throws; parameterless fallback succeeds.
 *
 * @internal
 */
final class ThrowingConfigComparatorSchemaManager
{
    public bool $fallbackCalled = false;

    public function __construct(private readonly Comparator $inner)
    {
    }

    public function createComparator(?ComparatorConfig $config = null): Comparator
    {
        if ($config instanceof ComparatorConfig) {
            throw new RuntimeException('forced config comparator failure');
        }

        $this->fallbackCalled = true;

        return $this->inner;
    }
}

/**
 * Test double that forces the DBAL 3 legacy setPrimaryKey() code path.
 *
 * @internal
 */
final class LegacyPrimaryKeyTableSchemaHelper extends TableSchemaHelper
{
    protected static function supportsPrimaryKeyConstraints(): bool
    {
        return false;
    }
}
