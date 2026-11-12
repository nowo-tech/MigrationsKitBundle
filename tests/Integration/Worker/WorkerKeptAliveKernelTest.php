<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Integration\Worker;

use Nowo\MigrationsKitBundle\Migration\CreateTablesService;
use Nowo\MigrationsKitBundle\Migration\SchemaChecker;
use Nowo\MigrationsKitBundle\Tests\Integration\Kernel\MigrationsKitTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

use function gc_collect_cycles;
use function implode;
use function memory_get_usage;
use function sys_get_temp_dir;

/**
 * REQ-RUNTIME-002: ONE kept-alive kernel, the services resetter never called. The shared {@see CreateTablesService}
 * and {@see SchemaChecker} serve 1,200 calls alternating two tenants (each with its own table and columns); the
 * collation command runs on the same kernel every tenth iteration. Each answer reflects only its own tenant and
 * memory stays flat.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS    = 1200;
    private const MEMORY_MARGIN = 4 * 1024 * 1024;

    private ?MigrationsKitTestKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-migrations-kit-test-kernel/legacy');
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-migrations-kit-test-kernel/legacy');
    }

    public function testAlternatingTenantsNeverShareStateAndMemoryStaysFlat(): void
    {
        $this->kernel = new MigrationsKitTestKernel('legacy');
        $this->kernel->boot();
        $container = $this->kernel->getContainer();
        $service   = $container->get(CreateTablesService::class);
        $checker   = $container->get(SchemaChecker::class);
        self::assertInstanceOf(CreateTablesService::class, $service);
        self::assertInstanceOf(SchemaChecker::class, $checker);
        $connection = $service->getConnection();

        foreach (['alice', 'bob'] as $tenant) {
            foreach ($service->apply($connection->createSchemaManager()->introspectSchema(), $this->definition($tenant, 0)) as $sql) {
                $connection->executeStatement($sql);
            }
        }

        $application = new Application($this->kernel);
        $command     = new CommandTester($application->find('nowo:migrations:check-collations'));

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $tenant = $i % 2 === 0 ? 'alice' : 'bob';
            $other  = $i % 2 === 0 ? 'bob' : 'alice';

            self::assertTrue($checker->tableExists('kit_' . $tenant), 'iteration ' . $i);
            self::assertTrue($checker->columnExists('kit_' . $tenant, $tenant . '_col'));
            self::assertFalse($checker->columnExists('kit_' . $tenant, $other . '_col'), 'iteration ' . $i . ' sees no column of the other tenant');
            self::assertFalse($checker->tableExists('kit_' . $tenant . '_' . $i), 'no table of an earlier iteration');

            // A new table per call: the generated SQL names only this iteration's tenant table.
            $sql = implode(';', $service->apply($connection->createSchemaManager()->introspectSchema(), $this->definition($tenant, $i + 1)));
            self::assertStringContainsString('kit_' . $tenant . '_' . ($i + 1), $sql);
            self::assertStringNotContainsString('kit_' . $other, $sql, 'iteration ' . $i . ' SQL carries no data of the other tenant');

            if ($i % 10 === 0) {
                self::assertSame(0, $command->execute([]));
            }

            if ($i === 100) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many calls');
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $tenant, int $n): array
    {
        $name = $n === 0 ? 'kit_' . $tenant : 'kit_' . $tenant . '_' . $n;

        return ['tables' => [$name => ['columns' => [
            ['name' => 'id', 'type' => 'integer'],
            ['name' => $tenant . '_col', 'type' => 'string', 'length' => 64],
        ], 'primary_key' => [['columns' => ['id']]]]]];
    }

    private function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }
}
