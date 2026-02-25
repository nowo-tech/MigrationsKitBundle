<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for MigrationsKitBundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_migrations_kit';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('connection')
                    ->info('Doctrine connection name used by CreateTablesService when injected from the container')
                    ->defaultValue('default')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
