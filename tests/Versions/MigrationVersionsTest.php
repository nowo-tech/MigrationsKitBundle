<?php

declare(strict_types=1);

namespace Nowo\MigrationsKitBundle\Tests\Versions;

use DateTimeImmutable;
use Nowo\MigrationsKitBundle\Versions\MigrationVersions;
use PHPUnit\Framework\TestCase;

final class MigrationVersionsTest extends TestCase
{
    public function testVersionOf(): void
    {
        self::assertSame('Version20261105120000', MigrationVersions::versionOf('migrations/Version20261105120000.php'));
        self::assertSame('Version_custom', MigrationVersions::versionOf('Version_custom.php'));
        self::assertNull(MigrationVersions::versionOf('migrations/.gitignore'));
        self::assertNull(MigrationVersions::versionOf('migrations/Helper.php'));
        self::assertNull(MigrationVersions::versionOf('migrations/Version20261105120000.php.bak'));
    }

    public function testMaxAddedAndViolations(): void
    {
        $base    = ['Version20261101000000', 'Version20261120170000'];
        $current = ['Version20261101000000', 'Version20261120170000', 'Version20261105090000', 'Version20261121000000'];

        self::assertNull(MigrationVersions::max([]));
        self::assertSame('Version20261120170000', MigrationVersions::max($base));
        self::assertSame(['Version20261105090000', 'Version20261121000000'], MigrationVersions::added($base, $current));
        self::assertSame(['Version20261105090000'], MigrationVersions::violations($base, $current));
        self::assertSame([], MigrationVersions::violations([], $current), 'No base migration: nothing can be out of order.');
        self::assertSame([], MigrationVersions::violations(['Version20261120170000'], ['Version20261120170000']), 'An unchanged migration is not added.');
    }

    public function testNext(): void
    {
        $now = new DateTimeImmutable('2026-11-05 10:00:00');

        self::assertSame('Version20261105100000', MigrationVersions::next([], $now));
        self::assertSame('Version20261105100000', MigrationVersions::next(['Version20261101000000'], $now));
        self::assertSame('Version20261120170001', MigrationVersions::next(['Version20261120170000', 'Version20261101000000'], $now), 'After a future version.');
        self::assertSame('Version20261231235959', MigrationVersions::next(['Version20261231235958'], $now));
        self::assertSame('Version20270101000000', MigrationVersions::next(['Version20261231235959'], $now), 'Rolls over the year.');
        self::assertSame('Version_custom1', MigrationVersions::next(['Version_custom'], $now), 'Non-timestamp names sort after digits.');
        self::assertSame('Version99999999999999', MigrationVersions::next(['Version99999999999998'], $now));
    }
}
