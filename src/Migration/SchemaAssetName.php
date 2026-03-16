<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use ReflectionObject;
use Throwable;

use function is_object;
use function method_exists;
use function property_exists;

/**
 * Helper to get the name from DBAL schema assets (Table, Column, Index, ForeignKey) in a way
 * compatible with DBAL 3, 4 and 5.
 *
 * In DBAL 4, {@see \Doctrine\DBAL\Schema\AbstractAsset::getName()} is deprecated; in DBAL 5 it is removed.
 * This helper reads the asset's name via reflection when possible to avoid triggering the deprecation,
 * then falls back to getName() for older DBAL, and to the public property when available (DBAL 5).
 *
 * @internal
 */
final class SchemaAssetName
{
    /**
     * Returns the name of a schema asset as string.
     *
     * Compatible with DBAL 3/4/5: tries reflection (no deprecation), then getName(), then property.
     *
     * @param object $asset Table|Column|Index|ForeignKeyConstraint (AbstractAsset)
     */
    public static function get(object $asset): string
    {
        $name = self::getNameViaReflection($asset);

        // DBAL 3: getName() when reflection did not yield a value (e.g. no 'name' property)
        if ($name === null && method_exists($asset, 'getName')) {
            $name = $asset->getName();
        }

        // DBAL 5: name may be public when getName() was removed
        if ($name === null && property_exists($asset, 'name')) {
            $name = $asset->name;
        }

        if ($name === null) {
            return '';
        }

        return self::nameToString($name);
    }

    /**
     * Read the 'name' property via reflection (including from parent classes) to avoid calling deprecated getName().
     */
    private static function getNameViaReflection(object $asset): mixed
    {
        try {
            $ref = new ReflectionObject($asset);
            while ($ref !== false) {
                if ($ref->hasProperty('name')) {
                    $prop  = $ref->getProperty('name');
                    $value = $prop->getValue($asset);
                    if ($value !== null && $value !== '') {
                        return $value;
                    }
                }
                $ref = $ref->getParentClass();
            }
        } catch (Throwable) {
            // ignore
        }

        return null;
    }

    private static function nameToString(mixed $name): string
    {
        if (is_object($name) && method_exists($name, 'toString')) {
            return $name->toString();
        }

        return (string) $name;
    }
}
