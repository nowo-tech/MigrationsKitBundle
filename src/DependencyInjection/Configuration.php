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
                ->arrayNode('collation_check')
                    ->info('nowo:migrations:check-collations: CI gate failing when a table or string column is not in the expected collation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->info('Doctrine connection checked; null = nowo_migrations_kit.connection')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('expected_collation')
                            ->info('Expected collation (e.g. utf8mb4_unicode_ci, or "C" / "en_US.utf8" on PostgreSQL); null = the connection doctrine default_table_options.collation')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('expected_charset')
                            ->info('Expected database character set / encoding (check_database only); null = default_table_options.charset, else on MySQL the collation prefix (utf8mb4)')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('check_columns')
                            ->info('Also compare every string column (MySQL / MariaDB), or columns with an explicit COLLATE (PostgreSQL)')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('check_database')
                            ->info('Also compare the database default collation / charset (the default of tables created without one)')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('ignore_tables')
                            ->info('Table names or fnmatch() patterns allowed to differ (not required to): tables still to convert. Their columns are ignored too.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('ignore_columns')
                            ->info('"table.column" names or fnmatch() patterns allowed to differ (e.g. a case-sensitive utf8mb4_bin token column)')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
