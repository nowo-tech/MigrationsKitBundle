<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test;

use Nowo\MigrationsKitBundle\Test\ExactKeyColumnsTestCase;
use Nowo\MigrationsKitBundle\Tests\Test\Fixture\FakeMySqlExactKeyColumnsTestCase;
use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The shipped gate as a bundle really uses it: extended, discovered and run by PHPUnit, with one declared identity
 * column that is binary NO PAD. This is the only test here that lets the base class's final test method be collected;
 * the deliberately failing variants are invoked by hand in {@see ExactKeyColumnsTestCaseTest}.
 */
#[CoversClass(ExactKeyColumnsTestCase::class)]
final class ExactKeyColumnsGateTest extends FakeMySqlExactKeyColumnsTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function declaredColumns(): array
    {
        return [
            new ExactKeyColumn('audit_entry', 'actor_id', 'nowo-tech/audit-kit-bundle', 'who acted'),
            new ExactKeyColumn('audit_entry', 'subject_id', 'nowo-tech/audit-kit-bundle', 'what was acted on'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaTables(): array
    {
        return ['audit_entry'];
    }

    /**
     * {@inheritdoc}
     */
    protected function schemaColumns(): array
    {
        return [
            $this->row('audit_entry', 'actor_id', 'utf8mb4_0900_bin'),
            $this->row('audit_entry', 'subject_id', null, 'binary(16)'),
        ];
    }
}
