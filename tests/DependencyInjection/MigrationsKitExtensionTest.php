<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\DependencyInjection;

use Nowo\MigrationsKitBundle\Collation\CollationChecker;
use Nowo\MigrationsKitBundle\Command\CheckCollationsCommand;
use Nowo\MigrationsKitBundle\DependencyInjection\MigrationsKitExtension;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Schema\Definition\SchemaDefinitionParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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

    /** Regression: the services are registered by the bundle and bound to the configured connection. */
    public function testLoadRegistersServicesBoundToConfiguredConnection(): void
    {
        $container = new ContainerBuilder();
        (new MigrationsKitExtension())->load([['connection' => 'legacy']], $container);

        self::assertTrue($container->hasDefinition(SchemaDefinitionParser::class));
        foreach ([CreateTablesService::class, SchemaChecker::class] as $serviceId) {
            $connection = $container->getDefinition($serviceId)->getArgument('$connection');
            self::assertInstanceOf(Reference::class, $connection);
            self::assertSame('doctrine.dbal.legacy_connection', (string) $connection);
        }
    }

    public function testGetAlias(): void
    {
        $extension = new MigrationsKitExtension();
        self::assertSame('nowo_migrations_kit', $extension->getAlias());
    }

    public function testCollationCheckArguments(): void
    {
        $container = new ContainerBuilder();
        (new MigrationsKitExtension())->load([['connection' => 'legacy', 'collation_check' => [
            'expected_collation' => 'utf8mb4_unicode_ci',
            'ignore_tables'      => ['uptime_*'],
        ]]], $container);

        $checker = $container->getDefinition(CollationChecker::class);
        self::assertSame('utf8mb4_unicode_ci', $checker->getArgument('$expectedCollation'));
        self::assertNull($checker->getArgument('$expectedCharset'));
        self::assertTrue($checker->getArgument('$checkColumns'));
        self::assertFalse($checker->getArgument('$checkDatabase'));
        self::assertSame(['uptime_*'], $checker->getArgument('$ignoreTables'));
        self::assertSame([], $checker->getArgument('$ignoreColumns'));

        $command = $container->getDefinition(CheckCollationsCommand::class);
        self::assertSame('legacy', $command->getArgument('$defaultConnection'));
        self::assertSame('doctrine', (string) $command->getArgument('$connections'));

        $container = new ContainerBuilder();
        (new MigrationsKitExtension())->load([['collation_check' => ['connection' => 'reporting']]], $container);
        self::assertSame('reporting', $container->getDefinition(CheckCollationsCommand::class)->getArgument('$defaultConnection'));
    }
}
