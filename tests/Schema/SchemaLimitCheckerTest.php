<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema;

use Nowo\MigrationsKitBundle\Schema\SchemaLimitChecker;
use PHPUnit\Framework\TestCase;

use const E_USER_WARNING;

class SchemaLimitCheckerTest extends TestCase
{
    private SchemaLimitChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new SchemaLimitChecker();
    }

    public function testCheckReturnsEmptyForNonMysql(): void
    {
        self::assertSame([], $this->checker->check(['tables' => []], 'sqlite'));
        self::assertSame([], $this->checker->check(['tables' => []], 'postgresql'));
        self::assertSame([], $this->checker->check(['tables' => []], 'pgsql'));
    }

    public function testCheckReturnsEmptyForValidDefinition(): void
    {
        $def = [
            'tables' => [
                'users' => [
                    'columns' => [
                        'id'    => ['type' => 'integer'],
                        'email' => ['type' => 'string', 'length' => 180],
                    ],
                    'indexes' => [
                        'idx_email' => ['columns' => ['email']],
                    ],
                ],
            ],
        ];
        self::assertSame([], $this->checker->check($def, 'mysql'));
    }

    public function testCheckWarnsWhenTooManyColumns(): void
    {
        $columns = [];
        for ($i = 0; $i < 1020; ++$i) {
            $columns['col_' . $i] = ['type' => 'integer'];
        }
        $def      = ['tables' => ['huge' => ['columns' => $columns, 'indexes' => []]]];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertCount(1, $warnings);
        self::assertStringContainsString('1017', $warnings[0]);
        self::assertStringContainsString('1020', $warnings[0]);
    }

    public function testCheckWarnsWhenRowSizeExceedsLimit(): void
    {
        $def = [
            'tables' => [
                'wide' => [
                    'columns' => [
                        'a' => ['type' => 'string', 'length' => 5000],
                        'b' => ['type' => 'string', 'length' => 5000],
                        'c' => ['type' => 'string', 'length' => 5000],
                        'd' => ['type' => 'string', 'length' => 5000],
                        'e' => ['type' => 'string', 'length' => 5000],
                    ],
                    'indexes' => [],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertNotEmpty($warnings);
        self::assertStringContainsString('row size', $warnings[0]);
    }

    public function testCheckWarnsWhenTooManyIndexes(): void
    {
        $indexes = [];
        for ($i = 0; $i < 70; ++$i) {
            $indexes['idx_' . $i] = ['columns' => ['id']];
        }
        $def = [
            'tables' => [
                't' => [
                    'columns' => ['id' => ['type' => 'integer']],
                    'indexes' => $indexes,
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertNotEmpty($warnings);
        self::assertStringContainsString('64', $warnings[0]);
    }

    public function testCheckWarnsWhenIndexHasTooManyColumns(): void
    {
        $cols = [];
        for ($i = 0; $i < 20; ++$i) {
            $cols[] = 'c' . $i;
        }
        $def = [
            'tables' => [
                't' => [
                    'columns' => array_combine($cols, array_fill(0, 20, ['type' => 'integer'])),
                    'indexes' => ['big_idx' => ['columns' => $cols]],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertNotEmpty($warnings);
        self::assertStringContainsString('16', $warnings[0]);
    }

    public function testCheckWarnsWhenIndexKeyLengthMayExceed(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id'   => ['type' => 'integer'],
                        'long' => ['type' => 'string', 'length' => 1000],
                    ],
                    'indexes' => [
                        'idx_long' => ['columns' => ['long']],
                    ],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertNotEmpty($warnings);
        self::assertStringContainsString('key length', $warnings[0]);
    }

    public function testCheckMariaDbIsTreatedAsMysql(): void
    {
        $def = ['tables' => []];
        self::assertSame([], $this->checker->check($def, 'mariadb'));
        self::assertSame([], $this->checker->check($def, 'MariaDB-10.5'));
    }

    public function testWarnIfOverLimitsTriggersWarnings(): void
    {
        $columns = [];
        for ($i = 0; $i < 1020; ++$i) {
            $columns['col_' . $i] = ['type' => 'integer'];
        }
        $def = ['tables' => ['huge' => ['columns' => $columns, 'indexes' => []]]];

        $raised = false;
        set_error_handler(static function (int $errno, string $errstr) use (&$raised): bool {
            if ($errno === E_USER_WARNING && str_contains($errstr, 'MigrationsKitBundle')) {
                $raised = true;
            }

            return true;
        });
        try {
            $this->checker->warnIfOverLimits($def, 'mysql');
        } finally {
            restore_error_handler();
        }
        self::assertTrue($raised);
    }

    public function testCheckSkipsInvalidTableDef(): void
    {
        $def = ['tables' => ['t' => 'not-array', 't2' => []]];
        self::assertSame([], $this->checker->check($def, 'mysql'));
    }

    public function testCheckIndexDefAsListUsesAsColumns(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'integer']],
                    'indexes' => ['i1' => ['a', 'b']],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertSame([], $warnings);
    }

    public function testEstimateRowSizeVariousTypes(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id'      => ['type' => 'integer'],
                        'name'    => ['type' => 'string', 'length' => 500],
                        'content' => ['type' => 'text'],
                        'data'    => ['type' => 'json'],
                        'bin'     => ['type' => 'blob'],
                        'amount'  => ['type' => 'decimal', 'precision' => 10, 'scale' => 2],
                        'ratio'   => ['type' => 'float'],
                        'birth'   => ['type' => 'datetime_immutable'],
                        'active'  => ['type' => 'boolean'],
                    ],
                    'indexes' => [],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertSame([], $warnings);
    }

    public function testEstimateIndexLengthUsesColumnType(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id'   => ['type' => 'integer'],
                        'code' => ['type' => 'string', 'length' => 100],
                    ],
                    'indexes' => [
                        'idx_code' => ['columns' => ['code']],
                        'idx_id'   => ['columns' => ['id']],
                    ],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertSame([], $warnings);
    }

    public function testEstimateRowSizeFallbackType(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id'      => ['type' => 'integer'],
                        'payload' => ['type' => 'guid'],
                    ],
                    'indexes' => [],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertSame([], $warnings);
    }

    public function testEstimateIndexLengthFallbackType(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id'         => ['type' => 'integer'],
                        'created_at' => ['type' => 'datetime_immutable'],
                    ],
                    'indexes' => [
                        'idx_created' => ['columns' => ['created_at']],
                    ],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertSame([], $warnings);
    }

    public function testWarnIfOverLimitsNoWarnings(): void
    {
        $def    = ['tables' => ['t' => ['columns' => ['id' => ['type' => 'integer']], 'indexes' => []]]];
        $raised = false;
        set_error_handler(static function (int $errno) use (&$raised): bool {
            if ($errno === E_USER_WARNING) {
                $raised = true;
            }

            return true;
        });
        try {
            $this->checker->warnIfOverLimits($def, 'mysql');
        } finally {
            restore_error_handler();
        }
        self::assertFalse($raised);
    }

    public function testCheckIndexColumnNotInTableUsesFallbackLength(): void
    {
        $def = [
            'tables' => [
                't' => [
                    'columns' => ['id' => ['type' => 'integer']],
                    'indexes' => [
                        'idx_other' => ['columns' => ['missing_column']],
                    ],
                ],
            ],
        ];
        $warnings = $this->checker->check($def, 'mysql');
        self::assertSame([], $warnings);
    }
}
