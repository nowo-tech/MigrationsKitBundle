<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\DependencyInjection;

use Nowo\MigrationsKitBundle\DependencyInjection\MigrationsKitExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    public function testGetAlias(): void
    {
        $extension = new MigrationsKitExtension();
        self::assertSame('nowo_migrations_kit', $extension->getAlias());
    }
}
