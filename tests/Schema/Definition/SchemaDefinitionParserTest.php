<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema\Definition;

use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;

class SchemaDefinitionParserTest extends TestCase
{
    private SchemaDefinitionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SchemaDefinitionParser();
    }

    public function testParseTableEmptyDefinition(): void
    {
        $table = $this->parser->parseTable('empty', []);
        self::assertSame('empty', $table->getName());
        self::assertCount(0, $table->getColumns());
    }

    public function testParseTableSingleTable(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'email', 'type' => 'string', 'length' => 180, 'notnull' => true],
            ],
            MDK::PRIMARY_KEY => [['columns' => ['id']]],
            MDK::INDEXES     => [
                ['columns' => ['email'], 'unique' => true, 'name' => 'uniq_email'],
            ],
        ];

        $table = $this->parser->parseTable('users', $tableDef);
        self::assertSame('users', $table->getName());
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('email'));
        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['id'], $table->getPrimaryKey()->getColumns());
        self::assertTrue($table->hasIndex('uniq_email'));
        self::assertTrue($table->getIndex('uniq_email')->isUnique());
    }

    public function testParseTableSkipsColumnWithoutType(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'name'],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
        self::assertFalse($table->hasColumn('name'));
    }

    public function testParseTableSkipsColumnWithDrop(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'legacy', 'type' => 'string', MDK::DROP => true],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
        self::assertFalse($table->hasColumn('legacy'));
    }

    public function testParseTableIndexWithoutUnique(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'code', 'type' => 'string', 'length' => 50],
            ],
            MDK::INDEXES => [
                ['columns' => ['code'], 'name' => 'idx_code'],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasIndex('idx_code'));
        self::assertFalse($table->getIndex('idx_code')->isUnique());
    }

    public function testParseTableIndexWithColumnsAsList(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'a', 'type' => 'integer'],
                ['name' => 'b', 'type' => 'integer'],
            ],
            MDK::INDEXES => [
                ['columns' => ['a', 'b'], 'name' => 'idx_ab'],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertSame(['a', 'b'], $table->getIndex('idx_ab')->getColumns());
    }

    public function testParseTableColumnOptions(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'amount', 'type' => 'decimal', 'precision' => 10, 'scale' => 2],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('amount'));
    }

    public function testParseTableForeignKey(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => [
                [
                    'columns'         => ['user_id'],
                    'foreign_table'   => 'users',
                    'foreign_columns' => ['id'],
                ],
            ],
        ];

        $table = $this->parser->parseTable('orders', $tableDef);
        self::assertCount(1, $table->getForeignKeys());
    }

    public function testParseTableColumnWithAllOptions(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                [
                    'name'          => 'id',
                    'type'          => 'integer',
                    'length'        => 11,
                    'precision'     => 10,
                    'scale'         => 2,
                    'notnull'       => true,
                    'default'       => 0,
                    'autoincrement' => true,
                    'comment'       => 'ID',
                    'unsigned'      => true,
                    'fixed'         => false,
                ],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
    }

    public function testParseTableIndexWithColumnsAsSingleString(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'code', 'type' => 'string', 'length' => 32],
            ],
            MDK::INDEXES => [
                ['columns' => 'code', 'name' => 'idx_code'],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasIndex('idx_code'));
        self::assertSame(['code'], $table->getIndex('idx_code')->getColumns());
    }

    public function testParseTableForeignKeyWithExplicitName(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => [
                [
                    'columns'         => ['user_id'],
                    'foreign_table'   => 'users',
                    'foreign_columns' => ['id'],
                    'name'            => 'fk_custom_name',
                ],
            ],
        ];

        $table = $this->parser->parseTable('orders', $tableDef);
        self::assertTrue($table->hasForeignKey('fk_custom_name'));
    }

    public function testParseTableSkipsPrimaryKeyItemWithDrop(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'alt_id', 'type' => 'integer'],
            ],
            MDK::PRIMARY_KEY => [
                ['columns' => ['id'], MDK::DROP => true],
                ['columns' => ['alt_id']],
            ],
        ];

        $table = $this->parser->parseTable('t', $tableDef);
        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['alt_id'], $table->getPrimaryKey()->getColumns());
    }

    public function testParseTableAcceptsForeignKeysKeyAlias(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
            ],
            MDK::PRIMARY_KEY => [['columns' => ['id']]],
            'foreign_keys'   => [
                [
                    'columns'         => ['user_id'],
                    'foreign_table'   => 'users',
                    'foreign_columns' => ['id'],
                ],
            ],
        ];

        $table = $this->parser->parseTable('orders', $tableDef);
        self::assertCount(1, $table->getForeignKeys());
    }

    public function testGetColumnOptionsReturnsOptionsFromColumnDef(): void
    {
        $col  = ['name' => 'id', 'type' => 'integer', 'notnull' => true, 'default' => 1, 'length' => 11, 'comment' => 'Primary key'];
        $opts = $this->parser->getColumnOptions($col);
        self::assertArrayHasKey('notnull', $opts);
        self::assertTrue($opts['notnull']);
        self::assertSame(1, $opts['default']);
        self::assertSame(11, $opts['length']);
        self::assertSame('Primary key', $opts['comment']);
    }

    public function testGetColumnAddArgsReturnsNameTypeAndOptions(): void
    {
        $col  = ['name' => 'email', 'type' => 'string', 'length' => 180];
        $args = $this->parser->getColumnAddArgs($col);
        self::assertSame('email', $args[0]);
        self::assertSame('string', $args[1]);
        self::assertIsArray($args[2]);
        self::assertSame(180, $args[2]['length']);
    }

    /** parseTable returns empty table when COLUMNS is not an array. */
    public function testParseTableColumnsNotArrayReturnsEmptyTable(): void
    {
        $table = $this->parser->parseTable('t', [MDK::COLUMNS => 'not_an_array']);
        self::assertSame('t', $table->getName());
        self::assertCount(0, $table->getColumns());
    }

    /** Index without name uses addIndex(cols) / addUniqueIndex(cols). */
    public function testParseTableIndexWithoutName(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'code', 'type' => 'string', 'length' => 32],
            ],
            MDK::INDEXES => [
                ['columns' => ['code']],
                ['columns' => ['code'], 'unique' => true],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertCount(2, $table->getIndexes());
    }

    /** Primary key item non-array or with DROP is skipped. */
    public function testParseTablePrimaryKeySkipsNonArrayAndDrop(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'alt', 'type' => 'integer'],
            ],
            MDK::PRIMARY_KEY => [
                'not_an_array',
                ['columns' => ['id'], MDK::DROP => true],
                ['columns' => ['alt']],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['alt'], $table->getPrimaryKey()->getColumns());
    }

    /** getColumnOptions with precision, scale, autoincrement, comment. */
    public function testGetColumnOptionsPrecisionScaleAutoincrementComment(): void
    {
        $col = [
            'name'          => 'id',
            'type'          => 'integer',
            'precision'     => 10,
            'scale'         => 2,
            'autoincrement' => true,
            'comment'       => 'PK',
        ];
        $opts = $this->parser->getColumnOptions($col);
        self::assertSame(10, $opts['precision']);
        self::assertSame(2, $opts['scale']);
        self::assertTrue($opts['autoincrement']);
        self::assertSame('PK', $opts['comment']);
    }

    /** parseTable FK without name uses addForeignKeyConstraint without name. */
    public function testParseTableForeignKeyWithoutName(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                ['name' => 'user_id', 'type' => 'integer', 'notnull' => true],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => [
                [
                    'columns'         => ['user_id'],
                    'foreign_table'   => 'users',
                    'foreign_columns' => ['id'],
                ],
            ],
        ];
        $table = $this->parser->parseTable('orders', $tableDef);
        self::assertCount(1, $table->getForeignKeys());
    }
}
