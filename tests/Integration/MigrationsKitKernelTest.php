<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Tests\Integration\Kernel\MigrationsKitTestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Regression: services.yaml registered no services, so the `connection` option was never applied and
 * CreateTablesService / SchemaChecker could not be injected from the container.
 */
final class MigrationsKitKernelTest extends TestCase
{
    private ?MigrationsKitTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
    }

    #[DataProvider('connectionProvider')]
    public function testServicesUseConfiguredConnection(string $connectionName): void
    {
        $container = $this->bootKernel($connectionName);
        $expected  = $container->get('doctrine.dbal.' . $connectionName . '_connection');
        self::assertInstanceOf(Connection::class, $expected);

        $service = $container->get(CreateTablesService::class);
        self::assertInstanceOf(CreateTablesService::class, $service);
        self::assertSame($expected, $service->getConnection());

        $checker = $container->get(SchemaChecker::class);
        self::assertInstanceOf(SchemaChecker::class, $checker);
        self::assertSame($expected, $checker->getConnection());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function connectionProvider(): iterable
    {
        yield 'default' => ['default'];
        yield 'legacy' => ['legacy'];
    }

    public function testContainerServicesCreateAndCheckTablesOnConfiguredConnection(): void
    {
        $container = $this->bootKernel('legacy');
        $service   = $container->get(CreateTablesService::class);
        $checker   = $container->get(SchemaChecker::class);
        self::assertInstanceOf(CreateTablesService::class, $service);
        self::assertInstanceOf(SchemaChecker::class, $checker);

        $connection = $service->getConnection();
        $definition = ['tables' => ['kit_settings' => ['columns' => [
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'name', 'type' => 'string', 'length' => 64],
        ], 'primary_key' => [['columns' => ['id']]]]]];

        self::assertFalse($checker->tableExists('kit_settings'));
        foreach ($service->apply($connection->createSchemaManager()->introspectSchema(), $definition) as $sql) {
            $connection->executeStatement($sql);
        }

        self::assertTrue($checker->tableExists('kit_settings'));
        self::assertTrue($checker->columnExists('kit_settings', 'name'));
        self::assertTrue($checker->hasPrimaryKey('kit_settings'));

        $default = $container->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $default);
        self::assertFalse((new SchemaChecker($default))->tableExists('kit_settings'));
    }

    public function testCheckCollationsCommandIsRegisteredAndSkipsSqlite(): void
    {
        $this->bootKernel('legacy');
        self::assertNotNull($this->kernel);
        $application = new Application($this->kernel);
        self::assertTrue($application->has('nowo:migrations:check-collations'));

        $tester = new CommandTester($application->find('nowo:migrations:check-collations'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('SKIP: no collation check for', $tester->getDisplay());
    }

    private function bootKernel(string $connectionName): ContainerInterface
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-migrations-kit-test-kernel/' . $connectionName);
        $this->kernel = new MigrationsKitTestKernel($connectionName);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }
}
