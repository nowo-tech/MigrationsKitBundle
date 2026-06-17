<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema\Definition;

use Doctrine\DBAL\Schema\Table;
use Nowo\MigrationsKitBundle\Migration\MigrationDefinitionKeys as MDK;
use Nowo\MigrationsKitBundle\Migration\SchemaAssetName;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use function is_array;
use function is_string;

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
        self::assertSame('empty', SchemaAssetName::get($table));
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
        self::assertSame('users', SchemaAssetName::get($table));
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
        self::assertSame(180, $args[2]['length']);
    }

    /** parseTable returns empty table when COLUMNS is not an array. */
    public function testParseTableColumnsNotArrayReturnsEmptyTable(): void
    {
        $table = $this->parser->parseTable('t', [MDK::COLUMNS => 'not_an_array']);
        self::assertSame('t', SchemaAssetName::get($table));
    }

    /** parseTable returns empty table when COLUMNS is null. */
    public function testParseTableColumnsNullReturnsEmptyTable(): void
    {
        $table = $this->parser->parseTable('t', [MDK::COLUMNS => null]);
        self::assertSame('t', SchemaAssetName::get($table));
        self::assertFalse($table->hasColumn('id'));
    }

    /** getColumnOptions includes default key when value is null (array_key_exists branch). */
    public function testGetColumnOptionsWithDefaultNull(): void
    {
        $col  = ['name' => 'x', 'type' => 'string', 'default' => null];
        $opts = $this->parser->getColumnOptions($col);
        self::assertArrayHasKey('default', $opts);
        self::assertNull($opts['default']);
    }

    /** parseTable skips non-array column items in COLUMNS list. */
    public function testParseTableSkipsNonArrayColumnItem(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                'not_an_array_item',
                ['name' => 'id', 'type' => 'integer'],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
        self::assertCount(1, $table->getColumns());
    }

    /** parseTable skips non-array index items. */
    public function testParseTableSkipsNonArrayIndexItem(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'c', 'type' => 'string', 'length' => 1],
            ],
            MDK::INDEXES => [
                'not_an_array',
                ['columns' => ['c'], 'name' => 'idx_c'],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasIndex('idx_c'));
        self::assertCount(1, $table->getIndexes());
    }

    /** parseTable skips FK when foreign_columns is empty. */
    public function testParseTableSkipsForeignKeyWithEmptyForeignColumns(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'ref_id', 'type' => 'integer'],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => [
                [
                    'columns'         => ['ref_id'],
                    'foreign_table'   => 'other',
                    'foreign_columns' => [],
                ],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertCount(0, $table->getForeignKeys());
    }

    /** parseTable skips FK when foreign_table is empty. */
    public function testParseTableSkipsForeignKeyWithEmptyForeignTable(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'ref_id', 'type' => 'integer'],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => [
                [
                    'columns'         => ['ref_id'],
                    'foreign_table'   => '',
                    'foreign_columns' => ['id'],
                ],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertCount(0, $table->getForeignKeys());
    }

    /** parseTable skips non-array FK items. */
    public function testParseTableSkipsNonArrayForeignKeyItem(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'user_id', 'type' => 'integer'],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => [
                'not_an_array',
                [
                    'columns'         => ['user_id'],
                    'foreign_table'   => 'users',
                    'foreign_columns' => ['id'],
                ],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertCount(1, $table->getForeignKeys());
    }

    /** parseTable when FOREIGN_KEYS is not an array treats it as no FKs. */
    public function testParseTableForeignKeysNotArrayYieldsNoFks(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
            ],
            MDK::PRIMARY_KEY  => [['columns' => ['id']]],
            MDK::FOREIGN_KEYS => 'not_an_array',
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertCount(0, $table->getForeignKeys());
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

    /** parseTable skips column when type is empty string. */
    public function testParseTableSkipsColumnWithEmptyType(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'empty_type', 'type' => ''],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
        self::assertFalse($table->hasColumn('empty_type'));
    }

    /** parseTable skips column when name is empty string. */
    public function testParseTableSkipsColumnWithEmptyName(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => '', 'type' => 'string'],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertTrue($table->hasColumn('id'));
        self::assertCount(1, $table->getColumns());
    }

    /** parseTable treats index with columns as empty string as empty list (no index added). */
    public function testParseTableIndexWithEmptyColumnsString(): void
    {
        $tableDef = [
            MDK::COLUMNS => [
                ['name' => 'id', 'type' => 'integer'],
                ['name' => 'c', 'type' => 'string', 'length' => 1],
            ],
            MDK::INDEXES => [
                ['columns' => '', 'name' => 'empty_cols'],
            ],
        ];
        $table = $this->parser->parseTable('t', $tableDef);
        self::assertFalse($table->hasIndex('empty_cols'));
        self::assertCount(0, $table->getIndexes());
    }

    /** parseTable FK with onUpdate option. */
    public function testParseTableForeignKeyWithOnUpdate(): void
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
                    'onDelete'        => 'CASCADE',
                    'onUpdate'        => 'CASCADE',
                ],
            ],
        ];
        $table = $this->parser->parseTable('orders', $tableDef);
        self::assertCount(1, $table->getForeignKeys());
    }

    /** setTablePrimaryKey with empty array returns early (covered via reflection). */
    public function testSetTablePrimaryKeyWithEmptyArrayEarlyReturn(): void
    {
        $table = new Table('t');
        $table->addColumn('id', 'integer');
        $ref    = new ReflectionMethod(SchemaDefinitionParser::class, 'setTablePrimaryKey');
        $parser = new SchemaDefinitionParser();
        $ref->invoke($parser, $table, []);
        self::assertNull($table->getPrimaryKey());
    }

    /** parseTable with named FK uses options-then-name param order when Table reports options as 4th param (DBAL 4 style). */
    public function testParseTableForeignKeyNamedWhenOptionsAreFourthParam(): void
    {
        $parser   = new SchemaDefinitionParserOptionsFirstFk();
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
                    'name'            => 'fk_user',
                ],
            ],
        ];
        $table = $parser->parseTable('orders', $tableDef);
        self::assertTrue($table->hasForeignKey('fk_user'));
    }

    /** parseTable with named FK uses name-then-options param order when Table reports name as 4th param (DBAL 3 style). */
    public function testParseTableForeignKeyNamedWhenNameIsFourthParam(): void
    {
        $parser   = new SchemaDefinitionParserNameFirstFk();
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
                    'name'            => 'fk_user_name_first',
                ],
            ],
        ];
        $table = $parser->parseTable('orders', $tableDef);
        self::assertTrue($table->hasForeignKey('fk_user_name_first'));
    }
}

/**
 * Table whose addForeignKeyConstraint has options as 4th param (DBAL 4 style) so parser uses the else branch.
 *
 * @internal
 *
 * @phpstan-ignore-next-line class.extendsFinal (Doctrine Table is not final at runtime in all DBAL versions)
 */
final class TableOptionsFirstFk extends Table
{
    /**
     * @param mixed $foreignTable
     * @param non-empty-list<string> $localColumnNames
     * @param non-empty-list<string> $foreignColumnNames
     * @param array<string,mixed> $options
     * @param mixed $name
     *
     * @phpstan-ignore-next-line return.type argument.type
     */
    public function addForeignKeyConstraint(
        $foreignTable,
        array $localColumnNames,
        array $foreignColumnNames,
        array $options = [],
        $name = null,
    ): Table {
        /** @var Table $result */
        $result = parent::addForeignKeyConstraint($foreignTable, $localColumnNames, $foreignColumnNames, $options, $name);

        return $result;
    }
}

/**
 * Parser that uses TableOptionsFirstFk so addForeignKeyConstraintToTable else branch is covered.
 *
 * @internal
 */
final class SchemaDefinitionParserOptionsFirstFk extends SchemaDefinitionParser
{
    protected function createTable(string $tableName): TableOptionsFirstFk
    {
        return new TableOptionsFirstFk($tableName);
    }
}

/**
 * Table whose addForeignKeyConstraint has name as 4th param (DBAL 3 style).
 *
 * @internal
 *
 * @phpstan-ignore-next-line class.extendsFinal
 */
final class TableNameFirstFk extends Table
{
    /**
     * @phpstan-ignore-next-line return.type argument.type
     */
    public function addForeignKeyConstraint(
        $foreignTable,
        array $localColumnNames,
        array $foreignColumnNames,
        $name = null,
        $options = [],
    ): Table {
        /** @var array<string,mixed> $opts */
        $opts = is_array($options) ? $options : [];
        /** @var string|null $constraintName */
        $constraintName = is_string($name) && $name !== '' ? $name : null;

        /** @var Table $result */
        $result = parent::addForeignKeyConstraint($foreignTable, $localColumnNames, $foreignColumnNames, $opts, $constraintName);

        return $result;
    }
}

/**
 * Parser that uses TableNameFirstFk so addForeignKeyConstraintToTable name-first branch is covered.
 *
 * @internal
 */
final class SchemaDefinitionParserNameFirstFk extends SchemaDefinitionParser
{
    protected function createTable(string $tableName): TableNameFirstFk
    {
        return new TableNameFirstFk($tableName);
    }
}
