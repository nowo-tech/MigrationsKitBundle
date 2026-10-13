<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Integration\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\MigrationsKitBundle\NowoMigrationsKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/** Real kernel (FrameworkBundle + DoctrineBundle with two in-memory SQLite connections). */
final class MigrationsKitTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $connectionName)
    {
        parent::__construct('test_' . $connectionName, false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new NowoMigrationsKitBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-migrations-kit-test-kernel/' . $this->connectionName . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-migrations-kit-test-kernel/' . $this->connectionName . '/log';
    }

    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'php_errors'           => ['log' => true],
        ]);
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['driver' => 'pdo_sqlite', 'memory' => true],
                    'legacy'  => ['driver' => 'pdo_sqlite', 'memory' => true],
                ],
            ],
        ]);
        $container->loadFromExtension('nowo_migrations_kit', ['connection' => $this->connectionName]);
    }
}
