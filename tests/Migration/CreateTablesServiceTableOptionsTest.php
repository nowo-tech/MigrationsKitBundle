<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function implode;

/**
 * A generated CREATE TABLE must carry the table options the host configured.
 *
 * Measured on a real host: of 311 CREATE TABLE statements dumped from the schema, 305 carried
 * COLLATE utf8mb4_unicode_ci once default_table_options was set, and 6 carried no table option at all - no
 * DEFAULT CHARACTER SET and no ENGINE. Those 6 (http_log_daily, http_log_event_seen, mail_kit_tracking_send,
 * nowo_short_link_click, nowo_short_link_click_daily, nowo_short_link_rollup_day) are the tables built through this
 * service, which passed a bare `new Table($name)` to the platform. A host that had done its part was then worse off
 * than one that had not, because it believed it was covered.
 */
#[CoversClass(CreateTablesService::class)]
final class CreateTablesServiceTableOptionsTest extends TestCase
{
    /**
     * @param array<string, mixed> $params
     */
    private function service(AbstractPlatform $platform, array $params = []): CreateTablesService
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('createComparator')->willReturn(new Comparator($platform));
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('getParams')->willReturn($params);

        return new CreateTablesService($connection, new SchemaDefinitionParser());
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            MDK::TABLES => [
                'http_log_daily' => [
                    MDK::COLUMNS => [
                        ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        ['name' => 'route', 'type' => 'string', 'length' => 255, 'notnull' => true],
                    ],
                    MDK::PRIMARY_KEY => [['columns' => ['id']]],
                ],
            ],
        ];
    }

    public function testAConfiguredHostSeesItsCharsetCollationAndEngineInTheGeneratedSql(): void
    {
        $service = $this->service(new MySQLPlatform(), [
            'defaultTableOptions' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB'],
        ]);

        $sql = implode("\n", $service->apply(new Schema(), $this->definition()));

        self::assertStringContainsString('CREATE TABLE http_log_daily', $sql);
        self::assertStringContainsString('DEFAULT CHARACTER SET utf8mb4', $sql);
        self::assertStringContainsString('COLLATE `utf8mb4_unicode_ci`', $sql);
        self::assertStringContainsString('ENGINE = InnoDB', $sql);
    }

    public function testAnUnconfiguredHostGetsExactlyTheSqlItGotBefore(): void
    {
        // The whole point of reading the options from the connection instead of inventing a default: a host that
        // configured nothing must see no new keys and no surprise DDL.
        $configured = $this->service(new MySQLPlatform(), [])->apply(new Schema(), $this->definition());
        $bare       = (new SchemaDefinitionParser())->parseTable('http_log_daily', $this->definition()[MDK::TABLES]['http_log_daily']);

        self::assertSame((new MySQLPlatform())->getCreateTableSQL($bare), $configured);
    }

    public function testSqliteIsUnaffectedByTableOptions(): void
    {
        $sqlite      = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->getDatabasePlatform();
        $withOptions = $this->service($sqlite, ['defaultTableOptions' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']])
            ->apply(new Schema(), $this->definition());
        $without = $this->service($sqlite, [])->apply(new Schema(), $this->definition());

        self::assertSame($without, $withOptions, 'SQLite has no table-level collation: the generated SQL must not change.');
    }

    public function testPostgreSqlIsUnaffectedByTableOptions(): void
    {
        $withOptions = $this->service(new PostgreSQLPlatform(), ['defaultTableOptions' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']])
            ->apply(new Schema(), $this->definition());
        $without = $this->service(new PostgreSQLPlatform(), [])->apply(new Schema(), $this->definition());

        self::assertSame($without, $withOptions, 'PostgreSQL has no table-level collation: the generated SQL must not change.');
    }
}
