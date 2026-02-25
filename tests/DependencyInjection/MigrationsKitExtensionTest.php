<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\DependencyInjection;

use Nowo\MigrationsKitBundle\DependencyInjection\MigrationsKitExtension;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class MigrationsKitExtensionTest extends TestCase
{
    public function testLoadSetsConnectionParameter(): void
    {
        $container = new ContainerBuilder();
        $extension = new MigrationsKitExtension();
        $extension->load([['connection' => 'default']], $container);

        self::assertTrue($container->hasParameter('nowo_migrations_kit.connection'));
        self::assertSame('default', $container->getParameter('nowo_migrations_kit.connection'));
    }

    public function testLoadWithCustomConnection(): void
    {
        $container = new ContainerBuilder();
        $extension = new MigrationsKitExtension();
        $extension->load([['connection' => 'tenant_conn']], $container);

        self::assertSame('tenant_conn', $container->getParameter('nowo_migrations_kit.connection'));
    }

    /** When CreateTablesService is registered (e.g. by app), extension injects the configured connection. */
    public function testLoadInjectsConnectionIntoCreateTablesServiceWhenRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.dbal.default_connection');
        $container->register(CreateTablesService::class)
            ->setArguments([new Reference('doctrine.dbal.default_connection'), new Definition(SchemaDefinitionParser::class)]);
        $extension = new MigrationsKitExtension();
        $extension->load([['connection' => 'default']], $container);

        $def           = $container->getDefinition(CreateTablesService::class);
        $args          = $def->getArguments();
        $connectionRef = $args['$connection'] ?? $args[0] ?? null;
        self::assertNotNull($connectionRef);
        self::assertEquals('doctrine.dbal.default_connection', (string) $connectionRef);
    }

    public function testGetAlias(): void
    {
        $extension = new MigrationsKitExtension();
        self::assertSame('nowo_migrations_kit', $extension->getAlias());
    }
}
