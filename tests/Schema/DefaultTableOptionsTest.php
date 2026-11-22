<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema;

use Doctrine\DBAL\Connection;
use Nowo\MigrationsKitBundle\Schema\DefaultTableOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What a host configured in doctrine.dbal.default_table_options, read the way DBAL itself reads it.
 *
 * Measured on a real host: after configuring default_table_options, 305 of 311 generated CREATE TABLE statements
 * carried COLLATE utf8mb4_unicode_ci and 6 carried nothing at all - not the collation, not even ENGINE. Those 6 were
 * the ones this bundle builds, and the shape of the gap is the point: the Table object was given NO default options,
 * so adding a collation key by hand would have left ENGINE missing for the next person to notice. Everything
 * Doctrine computes is passed through, not a list of the options someone remembered.
 */
#[CoversClass(DefaultTableOptions::class)]
final class DefaultTableOptionsTest extends TestCase
{
    /**
     * @param array<string, mixed> $params
     */
    private function connection(array $params): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn($params);

        return $connection;
    }

    public function testAHostThatConfiguredNothingGetsNoOptions(): void
    {
        self::assertSame([], DefaultTableOptions::forConnection($this->connection([])));
    }

    public function testEveryConfiguredOptionIsPassedThroughIncludingEngine(): void
    {
        $options = DefaultTableOptions::forConnection($this->connection([
            'defaultTableOptions' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB'],
        ]));

        self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB'], $options);
    }

    public function testTheConnectionCharsetIsTheCharsetOfLastResort(): void
    {
        // Exactly what AbstractSchemaManager::createSchemaConfig() does.
        $options = DefaultTableOptions::forConnection($this->connection([
            'charset'             => 'utf8mb4',
            'defaultTableOptions' => ['collation' => 'utf8mb4_unicode_ci'],
        ]));

        self::assertSame(['collation' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4'], $options);
    }

    public function testAnExplicitTableCharsetWinsOverTheConnectionCharset(): void
    {
        $options = DefaultTableOptions::forConnection($this->connection([
            'charset'             => 'latin1',
            'defaultTableOptions' => ['charset' => 'utf8mb4'],
        ]));

        self::assertSame(['charset' => 'utf8mb4'], $options);
    }

    public function testAnEmptyConnectionCharsetIsNotAnOption(): void
    {
        self::assertSame([], DefaultTableOptions::forConnection($this->connection(['charset' => ''])));
    }
}
