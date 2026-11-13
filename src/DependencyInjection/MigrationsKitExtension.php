<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\DependencyInjection;

use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyChecker;
use Nowo\MigrationsKitBundle\Collation\ExactKeyColumnsInterface;
use Nowo\MigrationsKitBundle\Command\CheckCollationsCommand;
use Nowo\MigrationsKitBundle\Command\CheckKeyCollationsCommand;
use Nowo\MigrationsKitBundle\DependencyInjection\Compiler\ExactKeyColumnsPass;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Versions\GitMigrationFiles;
use Nowo\MigrationsKitBundle\Versions\MonotonicVersionsChecker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Loads MigrationsKitBundle configuration and services.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MigrationsKitExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('nowo_migrations_kit.connection', $config['connection']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Both services are defined in services.yaml; bind them to the configured DBAL connection.
        $connection = new Reference('doctrine.dbal.' . $config['connection'] . '_connection');
        foreach ([CreateTablesService::class, SchemaChecker::class] as $serviceId) {
            $container->getDefinition($serviceId)->setArgument('$connection', $connection);
        }

        /** @var array{connection: string|null, expected_collation: string|null, expected_charset: string|null, check_columns: bool, check_database: bool, ignore_tables: list<string>, ignore_columns: list<string>} $collation */
        $collation = $config['collation_check'];
        $container->getDefinition(CollationChecker::class)
            ->setArgument('$expectedCollation', $collation['expected_collation'])
            ->setArgument('$expectedCharset', $collation['expected_charset'])
            ->setArgument('$checkColumns', $collation['check_columns'])
            ->setArgument('$checkDatabase', $collation['check_database'])
            ->setArgument('$ignoreTables', $collation['ignore_tables'])
            ->setArgument('$ignoreColumns', $collation['ignore_columns']);
        /** @var array{paths: list<string>, base: string|null} $versions */
        $versions = $config['monotonic_versions'];
        $container->getDefinition(GitMigrationFiles::class)->setArgument('$projectDir', '%kernel.project_dir%');
        $container->getDefinition(MonotonicVersionsChecker::class)
            ->setArgument('$paths', $versions['paths'])
            ->setArgument('$base', $versions['base']);

        $container->getDefinition(CheckCollationsCommand::class)
            ->setArgument('$connections', new Reference('doctrine'))
            ->setArgument('$defaultConnection', $collation['connection'] ?? $config['connection']);

        // Any installed bundle declaring its identity / access-key columns is discovered without host registration.
        $container->registerForAutoconfiguration(ExactKeyColumnsInterface::class)->addTag(ExactKeyColumnsPass::TAG);

        /** @var array{connection: string|null, ignore_columns: array<string, string>} $keys */
        $keys = $config['key_collation_check'];
        $container->getDefinition(ExactKeyChecker::class)->setArgument('$ignoreColumns', $keys['ignore_columns']);
        $container->getDefinition(CheckKeyCollationsCommand::class)
            ->setArgument('$connections', new Reference('doctrine'))
            ->setArgument('$defaultConnection', $keys['connection'] ?? $config['connection']);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
