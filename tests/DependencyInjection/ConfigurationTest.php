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
    }

    public function testCustomConnection(): void
    {
        $config = $this->processConfiguration([['connection' => 'custom_conn']]);
        self::assertSame('custom_conn', $config['connection']);
    }

    public function testAliasConstant(): void
    {
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
