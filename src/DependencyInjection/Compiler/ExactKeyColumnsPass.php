<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\DependencyInjection\Compiler;

use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects every installed bundle's identity / access-key declaration.
 *
 * Discovery is automatic: a service implementing ExactKeyColumnsInterface is tagged by autoconfiguration, so a host
 * registers nothing. If a host had to list each bundle by hand, the aggregate run would silently miss the bundles
 * nobody remembered — which is the blind spot the whole design exists to close.
 *
 * The same declaration also exempts those columns from the uniform collation gate, so a host never has to restate
 * them as ignore_columns: by construction the two cannot disagree.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ExactKeyColumnsPass implements CompilerPassInterface
{
    public const TAG = 'nowo_migrations_kit.exact_key_columns';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ExactKeyChecker::class)) {
            return;
        }

        $providers = new TaggedIteratorArgument(self::TAG);
        $container->getDefinition(ExactKeyChecker::class)->setArgument('$providers', $providers);

        if ($container->hasDefinition(CollationChecker::class)) {
            $container->getDefinition(CollationChecker::class)->setArgument('$exactKeyProviders', $providers);
        }
    }
}
