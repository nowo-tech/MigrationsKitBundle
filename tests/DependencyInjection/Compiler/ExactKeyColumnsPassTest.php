<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\DependencyInjection\Compiler;

use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\DependencyInjection\Compiler\ExactKeyColumnsPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(ExactKeyColumnsPass::class)]
final class ExactKeyColumnsPassTest extends TestCase
{
    public function testItWiresTheTaggedProvidersIntoBothCheckers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ExactKeyChecker::class, new Definition(ExactKeyChecker::class));
        $container->setDefinition(CollationChecker::class, new Definition(CollationChecker::class));

        (new ExactKeyColumnsPass())->process($container);

        $providers = $container->getDefinition(ExactKeyChecker::class)->getArgument('$providers');
        self::assertInstanceOf(TaggedIteratorArgument::class, $providers);
        self::assertSame(ExactKeyColumnsPass::TAG, $providers->getTag());
        self::assertSame($providers, $container->getDefinition(CollationChecker::class)->getArgument('$exactKeyProviders'));
    }

    public function testTheUniformCollationCheckerIsOptional(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ExactKeyChecker::class, new Definition(ExactKeyChecker::class));

        (new ExactKeyColumnsPass())->process($container);

        self::assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition(ExactKeyChecker::class)->getArgument('$providers'));
        self::assertFalse($container->hasDefinition(CollationChecker::class));
    }

    public function testItDoesNothingWhenTheBundleServicesAreNotLoaded(): void
    {
        $container = new ContainerBuilder();

        (new ExactKeyColumnsPass())->process($container);

        self::assertFalse($container->hasDefinition(ExactKeyChecker::class));
    }
}
