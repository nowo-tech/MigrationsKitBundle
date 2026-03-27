<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Migration;

use Nowo\MigrationsKitBundle\Migration\SchemaAssetName;
use PHPUnit\Framework\TestCase;
use stdClass;

class SchemaAssetNameTest extends TestCase
{
    public function testGetReturnsNameFromReflectionWhenAssetHasNameProperty(): void
    {
        $table = new \Doctrine\DBAL\Schema\Table('users');
        self::assertSame('users', SchemaAssetName::get($table));
    }

    public function testGetUsesGetNameWhenReflectionDoesNotYieldName(): void
    {
        $asset = new class {
            public function getName(): string
            {
                return 'from_get_name';
            }
        };
        self::assertSame('from_get_name', SchemaAssetName::get($asset));
    }

    public function testGetUsesPublicNamePropertyWhenReflectionAndGetNameFail(): void
    {
        $asset       = new stdClass();
        $asset->name = 'from_public_property';
        self::assertSame('from_public_property', SchemaAssetName::get($asset));
    }

    public function testGetReturnsEmptyStringWhenNoNameAvailable(): void
    {
        $asset = new stdClass();
        self::assertSame('', SchemaAssetName::get($asset));
    }

    public function testGetConvertsNameObjectWithToStringToString(): void
    {
        $nameValue = new class {
            public function toString(): string
            {
                return 'id';
            }
        };
        $asset = new class($nameValue) {
            public function __construct(public object $name)
            {
            }
        };
        self::assertSame($nameValue, $asset->name);
        self::assertSame('id', SchemaAssetName::get($asset));
    }

    /**
     * When no name is available (reflection null, no getName(), property null), get() returns empty string.
     */
    public function testGetReturnsEmptyWhenPublicNamePropertyIsNull(): void
    {
        $asset       = new stdClass();
        $asset->name = null;
        self::assertSame('', SchemaAssetName::get($asset));
    }
}
