<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Migration;

use function is_object;
use function method_exists;
use function property_exists;

/**
 * Helper to get the name from DBAL schema assets (Table, Column, Index, ForeignKey) in a way
 * compatible with DBAL 3, 4 and 5.
 *
 * In DBAL 5, {@see \Doctrine\DBAL\Schema\AbstractAsset::getName()} is removed. This helper
 * uses the new API (e.g. public property) when available and falls back to getName() for older versions.
 *
 * @internal
 */
final class SchemaAssetName
{
    /**
     * Returns the name of a schema asset as string.
     *
     * Compatible with DBAL 3/4 (getName()) and DBAL 5 (property or new API).
     *
     * @param object $asset Table|Column|Index|ForeignKeyConstraint (AbstractAsset)
     */
    public static function get(object $asset): string
    {
        $name = null;

        // DBAL 3/4: getName() (deprecated in 4, removed in 5)
        if (method_exists($asset, 'getName')) {
            $name = $asset->getName();
        }

        // DBAL 5: getName() removed; name may be exposed as public property or new API
        if ($name === null && property_exists($asset, 'name')) {
            $name = $asset->name;
        }

        if ($name === null) {
            return '';
        }

        return is_object($name) && method_exists($name, 'toString')
            ? $name->toString()
            : (string) $name;
    }
}
