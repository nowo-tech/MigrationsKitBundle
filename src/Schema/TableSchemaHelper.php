<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use ReflectionMethod;
use Throwable;

/**
 * DBAL version-compatible helpers for schema operations used by the migrations kit.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TableSchemaHelper
{
    /**
     * @param list<string>|non-empty-list<string> $columnNames
     */
    public static function setPrimaryKey(Table $table, array $columnNames, ?string $constraintName = null): void
    {
        if ($columnNames === []) {
            return;
        }

        if (static::supportsPrimaryKeyConstraints()) {
            $editor = PrimaryKeyConstraint::editor();
            if ($constraintName !== null && $constraintName !== '') {
                $editor = $editor->setUnquotedName($constraintName);
            }
            // Column names come from schema definitions; non-empty at runtime (PHPStan: list<string> vs non-empty-string).
            /* @phpstan-ignore argument.type */
            $table->addPrimaryKeyConstraint($editor->setUnquotedColumnNames(...$columnNames)->create());

            return;
        }

        if ($constraintName !== null && $constraintName !== '') {
            $table->setPrimaryKey($columnNames, $constraintName);
        } else {
            $table->setPrimaryKey($columnNames);
        }
    }

    public static function createSchemaComparator(object $schemaManager): Comparator
    {
        if (!method_exists($schemaManager, 'createComparator')) {
            throw new InvalidArgumentException('Schema manager must provide createComparator().');
        }

        if (class_exists(ComparatorConfig::class)) {
            $config = (new ComparatorConfig())->withReportModifiedIndexes(false);

            try {
                $method = new ReflectionMethod($schemaManager, 'createComparator');
                if ($method->getNumberOfParameters() > 0) {
                    /** @var Comparator $comparator */
                    $comparator = $schemaManager->createComparator($config);

                    return $comparator;
                }
            } catch (Throwable) {
            }
        }

        /** @var Comparator $comparator */
        $comparator = $schemaManager->createComparator();

        return $comparator;
    }

    protected static function supportsPrimaryKeyConstraints(): bool
    {
        return class_exists(PrimaryKeyConstraint::class);
    }
}
