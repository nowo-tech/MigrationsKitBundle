<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use Nowo\MigrationsKitContracts\Collation\ExactKeyStatus;

use function sprintf;

/**
 * What the check found for the OWN DEFAULT of a table that holds a declared identity / access-key column.
 *
 * Separate from {@see ExactKeyFinding} because the subject is a table, not a column, and the damage is in a different
 * tense: the columns declared today are correct (that is what the column findings say), while the table default is
 * what the first text column added tomorrow without an explicit COLLATE will inherit. By then the commit that
 * created the table is months old. The commit that creates the table is the only moment at which this is cheap to
 * fix, which is why it is a failure of the gate and not a warning beside it.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactKeyTableFinding
{
    /**
     * @param string $actual the table's own default collation, "" when the catalogue has no value for it
     * @param string $detail one line: what is wrong, and why it matters later
     * @param string|null $alter the statement that fixes it, null when the fix needs a decision from the host first
     */
    public function __construct(
        public string $table,
        public ExactKeyStatus $status,
        public string $actual = '',
        public string $detail = '',
        public ?string $alter = null,
    ) {
    }

    public function describe(): string
    {
        return sprintf(
            '%-8s %s (table default)%s%s',
            $this->status->value,
            $this->table,
            $this->actual === '' ? '' : ': ' . $this->actual,
            $this->detail === '' ? '' : ' (' . $this->detail . ')',
        );
    }
}
