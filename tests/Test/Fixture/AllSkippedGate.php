<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Test\Fixture;

use Nowo\MigrationsKitContracts\Collation\ExactKeyColumn;

/**
 * Every declared column belongs to a mode that is off, so the run checked nothing: the gate must not report green.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AllSkippedGate extends FakeMySqlExactKeyColumnsTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function declaredColumns(): array
    {
        return [new ExactKeyColumn('chat_user_reference', 'user_key', 'nowo-tech/chat-kit-bundle', 'user key', 'nowo_chat_kit.user_reference: string', false)];
    }
}
