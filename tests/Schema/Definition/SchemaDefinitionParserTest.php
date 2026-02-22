<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Schema\Definition;

use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;

class SchemaDefinitionParserTest extends TestCase
{
    private SchemaDefinitionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SchemaDefinitionParser();
    }

    public function testParseEmptyDefinition(): void
    {
        $schema = $this->parser->parse([]);
        self::assertCount(0, $schema->getTables());
    }

    public function testParseSingleTable(): void
    {
        $definition = [
            'tables' => [
                'users' => [
                    'columns' => [
                        'id' => ['type' => 'integer', 'autoincrement' => true, 'notnull' => true],
                        'email' => ['type' => 'string', 'length' => 180, 'notnull' => true],
                    ],
                    'primary_key' => ['id'],
                    'indexes' => [
                        'uniq_email' => ['columns' => ['email'], 'unique' => true],
                    ],
                ],
            ],
        ];

        $schema = $this->parser->parse($definition);
        self::assertTrue($schema->hasTable('users'));
        $table = $schema->getTable('users');
        self::assertTrue($table->hasColumn('id'));
        self::assertTrue($table->hasColumn('email'));
        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['id'], $table->getPrimaryKey()->getColumns());
        self::assertTrue($table->hasIndex('uniq_email'));
        self::assertTrue($table->getIndex('uniq_email')->isUnique());
    }

    public function testParseSkipsTableWithoutColumns(): void
    {
        $definition = [
            'tables' => [
                'empty' => [],
                'valid' => [
                    'columns' => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        self::assertFalse($schema->hasTable('empty'));
        self::assertTrue($schema->hasTable('valid'));
    }

    public function testParseSkipsInvalidTableDef(): void
    {
        $definition = [
            'tables' => [
                'invalid' => 'not-array',
            ],
        ];
        $schema = $this->parser->parse($definition);
        self::assertCount(0, $schema->getTables());
    }

    public function testParseSkipsColumnWithoutType(): void
    {
        $definition = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id' => ['type' => 'integer'],
                        'name' => [],
                    ],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        $table = $schema->getTable('t');
        self::assertTrue($table->hasColumn('id'));
        self::assertFalse($table->hasColumn('name'));
    }

    public function testParseIndexWithoutUnique(): void
    {
        $definition = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id' => ['type' => 'integer'],
                        'code' => ['type' => 'string', 'length' => 50],
                    ],
                    'indexes' => [
                        'idx_code' => ['columns' => ['code']],
                    ],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        $table = $schema->getTable('t');
        self::assertTrue($table->hasIndex('idx_code'));
        self::assertFalse($table->getIndex('idx_code')->isUnique());
    }

    public function testParseIndexWithColumnsAsList(): void
    {
        $definition = [
            'tables' => [
                't' => [
                    'columns' => [
                        'a' => ['type' => 'integer'],
                        'b' => ['type' => 'integer'],
                    ],
                    'indexes' => [
                        'idx_ab' => ['a', 'b'],
                    ],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        $table = $schema->getTable('t');
        self::assertSame(['a', 'b'], $table->getIndex('idx_ab')->getColumns());
    }

    public function testParseColumnOptions(): void
    {
        $definition = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id' => [
                            'type' => 'integer',
                            'autoincrement' => true,
                            'notnull' => true,
                        ],
                        'amount' => [
                            'type' => 'decimal',
                            'precision' => 10,
                            'scale' => 2,
                        ],
                    ],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        $table = $schema->getTable('t');
        self::assertTrue($table->hasColumn('amount'));
    }

    public function testParseTableOptions(): void
    {
        $definition = [
            'tables' => [
                't' => [
                    'columns' => ['id' => ['type' => 'integer']],
                    'options' => ['charset' => 'utf8mb4'],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        self::assertTrue($schema->hasTable('t'));
    }

    public function testParseColumnWithAllOptions(): void
    {
        $definition = [
            'tables' => [
                't' => [
                    'columns' => [
                        'id' => [
                            'type' => 'integer',
                            'length' => 11,
                            'precision' => 10,
                            'scale' => 2,
                            'notnull' => true,
                            'default' => 0,
                            'autoincrement' => true,
                            'comment' => 'ID',
                            'unsigned' => true,
                            'fixed' => false,
                        ],
                    ],
                ],
            ],
        ];
        $schema = $this->parser->parse($definition);
        self::assertTrue($schema->hasTable('t'));
        self::assertTrue($schema->getTable('t')->hasColumn('id'));
    }
}
