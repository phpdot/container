<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\Scanner\AttributeScanner;
use PHPdot\Container\Scope;
use PHPdot\Container\Tests\Fixtures\Attribute\ScopedFixture;
use PHPdot\Container\Tests\Fixtures\Attribute\SingletonFixture;
use PHPdot\Container\Tests\Fixtures\Attribute\TransientFixture;
use PHPdot\Container\Tests\Fixtures\Attribute\UntaggedFixture;
use PHPUnit\Framework\TestCase;

final class AttributeTest extends TestCase
{
    private AttributeScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new AttributeScanner();
    }

    public function testScansSingletonScopedAndTransientFromDirectory(): void
    {
        $results = $this->scanner->scanDirectory(__DIR__ . '/Fixtures/Attribute');

        $this->assertSame(Scope::SINGLETON, $results[SingletonFixture::class] ?? null);
        $this->assertSame(Scope::SCOPED, $results[ScopedFixture::class] ?? null);
        $this->assertSame(Scope::TRANSIENT, $results[TransientFixture::class] ?? null);
    }

    public function testIgnoresClassesWithoutLifecycleAttributes(): void
    {
        $results = $this->scanner->scanDirectory(__DIR__ . '/Fixtures/Attribute');

        $this->assertArrayNotHasKey(UntaggedFixture::class, $results);
    }
}
