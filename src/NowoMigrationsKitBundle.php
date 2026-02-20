<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle;

use Nowo\MigrationsKitBundle\DependencyInjection\MigrationsKitExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle providing migration helpers for Doctrine Migrations.
 *
 * Provides schema checks (table exists, column exists, index exists) and
 * array-based migration definitions so you can write migrations by defining
 * an array instead of raw SQL. Compatible with DBAL 2.x, 3.x, 4.x and
 * doctrine/migrations 3.x and 4.x.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class NowoMigrationsKitBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new MigrationsKitExtension();
    }
}
