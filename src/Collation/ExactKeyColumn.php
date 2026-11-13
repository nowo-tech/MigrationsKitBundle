<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Collation;

use InvalidArgumentException;

use function sprintf;
use function strtolower;
use function trim;

/**
 * One column a bundle declares as an identity or access key (REQ-SEC-006).
 *
 * Identity and access-key columns are compared for equality, never sorted or searched case-insensitively: a user id,
 * a tenant key, a token, a share slug, an API key hash. They must therefore live in a binary NO PAD collation, so that
 * "abc" and "abc   " are two different keys. A PAD SPACE collation (including the binary utf8mb4_bin) folds them, and
 * an accent / case-insensitive one (utf8mb4_0900_ai_ci, the MySQL 8+ server default a column with no COLLATE inherits)
 * folds far more.
 *
 * The declaration is positive on purpose: a column is only checked once a bundle names it here, and naming it is what
 * forces the collation decision. Scanning for "bad" columns can never see a column nobody thought about.
 *
 * $table is the table name the bundle resolves at runtime, never a literal: hosts rename tables and apply prefixes
 * (Workflow's table_prefix, ChatKit's conversation table), and a literal makes an aggregate host-side run report false
 * failures on exactly those hosts. Build the declaration from the same configuration the schema is built from.
 *
 * $condition / $active describe a column that only exists in some configurations (TaskBoard's user_reference: id,
 * ChatKit's string mode). An inactive column is reported as skipped with its condition, never as a failure, while an
 * active column whose table is absent IS a failure: that is a renamed or dropped table, not a disabled feature.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ExactKeyColumn
{
    /**
     * @param string $table resolved table name (configuration applied: prefix, host-chosen name)
     * @param string $column column name
     * @param string $owner package or bundle that owns the table, e.g. "nowo-tech/audit-kit-bundle"
     * @param string $purpose one line saying what the key is, shown in the report
     * @param string|null $condition configuration that gates the column, e.g. "nowo_chat_kit.user_reference: string"
     * @param bool $active false when $condition is not met in this configuration: reported as skipped, never failed
     *
     * @throws InvalidArgumentException when table, column or owner is empty
     */
    public function __construct(
        public string $table,
        public string $column,
        public string $owner,
        public string $purpose = '',
        public ?string $condition = null,
        public bool $active = true,
    ) {
        foreach (['table' => $table, 'column' => $column, 'owner' => $owner] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('ExactKeyColumn: $%s cannot be empty.', $name));
            }
        }
    }

    /**
     * Lower-cased "table.column", the key the check matches information_schema rows on.
     */
    public function key(): string
    {
        return strtolower($this->table . '.' . $this->column);
    }

    public function describe(): string
    {
        return $this->table . '.' . $this->column;
    }
}
