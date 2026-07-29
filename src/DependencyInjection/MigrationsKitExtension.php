<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\DependencyInjection;

use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
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

        $connectionId = 'doctrine.dbal.' . $config['connection'] . '_connection';
        if ($container->hasDefinition(CreateTablesService::class)) {
            $container->getDefinition(CreateTablesService::class)
                ->setArgument('$connection', new Reference($connectionId));
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
