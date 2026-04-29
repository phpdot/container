<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use function PHPdot\Container\vendor;

use PHPUnit\Framework\TestCase;

final class VendorFunctionTest extends TestCase
{
    public function testReturnsVendorDirWhenCalledWithoutArguments(): void
    {
        $vendorDir = vendor();

        self::assertNotSame('', $vendorDir);
        self::assertDirectoryExists($vendorDir);
        self::assertDirectoryExists($vendorDir . '/composer');
    }

    public function testJoinsRelativeSegmentToVendorDir(): void
    {
        $path = vendor('autoload.php');

        self::assertSame(vendor() . '/autoload.php', $path);
        self::assertFileExists($path);
    }

    public function testStripsLeadingSlashFromRelativeSegment(): void
    {
        $without = vendor('autoload.php');
        $with = vendor('/autoload.php');

        self::assertSame($without, $with);
    }

    public function testReturnsAbsolutePath(): void
    {
        self::assertStringStartsWith('/', vendor());
    }
}
