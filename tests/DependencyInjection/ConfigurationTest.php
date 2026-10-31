<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\DependencyInjection;

use Nowo\MigrationsKitBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = $this->processConfiguration([]);
        self::assertSame('default', $config['connection']);
        self::assertSame([
            'connection'         => null,
            'expected_collation' => null,
            'expected_charset'   => null,
            'check_columns'      => true,
            'check_database'     => false,
            'ignore_tables'      => [],
            'ignore_columns'     => [],
        ], $config['collation_check']);
    }

    public function testCollationCheck(): void
    {
        $config = $this->processConfiguration([['collation_check' => [
            'expected_collation' => 'utf8mb4_unicode_ci',
            'ignore_tables'      => ['uptime_*'],
            'ignore_columns'     => ['users.token'],
            'check_database'     => true,
        ]]]);
        self::assertSame('utf8mb4_unicode_ci', $config['collation_check']['expected_collation']);
        self::assertSame(['uptime_*'], $config['collation_check']['ignore_tables']);
        self::assertSame(['users.token'], $config['collation_check']['ignore_columns']);
        self::assertTrue($config['collation_check']['check_database']);
    }

    public function testCustomConnection(): void
    {
        $config = $this->processConfiguration([['connection' => 'custom_conn']]);
        self::assertSame('custom_conn', $config['connection']);
    }

    public function testAliasConstant(): void
    {
        /* @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertSame('nowo_migrations_kit', Configuration::ALIAS);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function processConfiguration(array $configs): array
    {
        $processor     = new Processor();
        $configuration = new Configuration();

        return $processor->processConfiguration($configuration, $configs);
    }
}
