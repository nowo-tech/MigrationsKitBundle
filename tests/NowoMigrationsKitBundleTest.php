<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests;

use Nowo\MigrationsKitBundle\DependencyInjection\MigrationsKitExtension;
use Nowo\MigrationsKitBundle\NowoMigrationsKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class NowoMigrationsKitBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsExtension(): void
    {
        $bundle    = new NowoMigrationsKitBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertInstanceOf(MigrationsKitExtension::class, $extension);
    }

    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $bundle = new NowoMigrationsKitBundle();
        self::assertSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
    }
}
