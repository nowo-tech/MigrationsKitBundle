<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\DependencyInjection;

use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
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
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
