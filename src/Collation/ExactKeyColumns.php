<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use function array_values;

/**
 * A fixed list of declared identity / access-key columns.
 *
 * Enough for a bundle whose table names do not depend on configuration. A bundle whose host can rename or prefix its
 * tables writes its own {@see ExactKeyColumnsInterface} service instead and builds the list from the same configured
 * names its schema is built from, so the declaration follows the real table.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactKeyColumns implements ExactKeyColumnsInterface
{
    /** @var list<ExactKeyColumn> */
    private array $columns;

    public function __construct(ExactKeyColumn ...$columns)
    {
        $this->columns = array_values($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function exactKeyColumns(): array
    {
        return $this->columns;
    }
}
