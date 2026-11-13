<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use function sprintf;

/**
 * What the check found for one declared identity / access-key column.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactKeyFinding
{
    /**
     * @param string $actual collation found, "(none)" for a column with no collation, "" when nothing was found
     * @param string $detail one line of context: the condition that is off, the host ignore reason, the column type
     * @param string|null $alter ALTER TABLE statement that fixes it, null when there is nothing to fix
     */
    public function __construct(
        public ExactKeyColumn $column,
        public ExactKeyStatus $status,
        public string $actual = '',
        public string $detail = '',
        public ?string $alter = null,
    ) {
    }

    public function describe(): string
    {
        return sprintf(
            '%-8s %s%s%s',
            $this->status->value,
            $this->column->describe(),
            $this->actual === '' ? '' : ': ' . $this->actual,
            $this->detail === '' ? '' : ' (' . $this->detail . ')',
        );
    }
}
