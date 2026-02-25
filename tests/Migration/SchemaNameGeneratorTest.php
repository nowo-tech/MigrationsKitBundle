<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Nowo\MigrationsKitBundle\Migration\SchemaNameGenerator;
use PHPUnit\Framework\TestCase;

class SchemaNameGeneratorTest extends TestCase
{
    public function testGeneratePKNameReturnsDeterministicNameWithPrefix(): void
    {
        $name = SchemaNameGenerator::generatePKName('users', ['id']);
        self::assertStringStartsWith('PK_', $name);
        self::assertSame($name, SchemaNameGenerator::generatePKName('users', ['id']));
        self::assertNotSame(SchemaNameGenerator::generatePKName('users', ['id']), SchemaNameGenerator::generatePKName('users', ['email']));
    }

    public function testGenerateIndexNameReturnsDeterministicNameWithPrefix(): void
    {
        $name = SchemaNameGenerator::generateIndexName('users', ['email']);
        self::assertStringStartsWith('IDX_', $name);
        self::assertSame($name, SchemaNameGenerator::generateIndexName('users', ['email']));
    }

    public function testGenerateForeignKeyNameReturnsDeterministicNameWithPrefix(): void
    {
        $name = SchemaNameGenerator::generateForeignKeyName('orders', ['user_id']);
        self::assertStringStartsWith('FK_', $name);
        self::assertSame($name, SchemaNameGenerator::generateForeignKeyName('orders', ['user_id']));
    }

    public function testGeneratePKNameWithMultipleColumns(): void
    {
        $name = SchemaNameGenerator::generatePKName('order_items', ['order_id', 'product_id']);
        self::assertStringStartsWith('PK_', $name);
        self::assertSame(3 + 16, strlen($name)); // PK_ + 16-char hash
    }
}
