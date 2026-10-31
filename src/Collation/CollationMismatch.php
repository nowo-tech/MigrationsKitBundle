<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use function sprintf;

/**
 * One database object whose collation (or character set) differs from the expected one.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CollationMismatch
{
    public const SCOPE_DATABASE = 'database';
    public const SCOPE_TABLE    = 'table';
    public const SCOPE_COLUMN   = 'column';

    /**
     * @param self::SCOPE_* $scope
     * @param string $property "collation" or "charset"
     * @param bool $ignored true when the table / column is listed in ignore_tables / ignore_columns
     */
    public function __construct(
        public string $scope,
        public string $object,
        public string $property,
        public string $actual,
        public string $expected,
        public bool $ignored = false,
    ) {
    }

    public function describe(): string
    {
        return sprintf('%s %s: %s %s (expected %s)', $this->scope, $this->object, $this->property, $this->actual, $this->expected);
    }
}
