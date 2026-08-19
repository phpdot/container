<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use function PHPdot\Container\vendor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VendorFunctionTest extends TestCase
{
    #[Test]
    public function returnsVendorDirWhenCalledWithoutArguments(): void
    {
        $vendorDir = vendor();

        self::assertNotSame('', $vendorDir);
        self::assertDirectoryExists($vendorDir);
        self::assertDirectoryExists($vendorDir . '/composer');
    }

    #[Test]
    public function joinsRelativeSegmentToVendorDir(): void
    {
        $path = vendor('autoload.php');

        self::assertSame(vendor() . '/autoload.php', $path);
        self::assertFileExists($path);
    }

    #[Test]
    public function stripsLeadingSlashFromRelativeSegment(): void
    {
        $without = vendor('autoload.php');
        $with = vendor('/autoload.php');

        self::assertSame($without, $with);
    }

    #[Test]
    public function returnsAbsolutePath(): void
    {
        self::assertStringStartsWith('/', vendor());
    }
}
