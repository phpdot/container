<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Container\Attribute\Transient;
use PHPdot\Container\Scanner\AttributeScanner;
use PHPdot\Container\Scope;
use PHPUnit\Framework\TestCase;

final class AttributeTest extends TestCase
{
    private AttributeScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new AttributeScanner();
    }

    public function testDetectsSingletonAttribute(): void
    {
        $scope = $this->scanner->getScopeFromAttributes(SingletonClass::class);
        $this->assertSame(Scope::SINGLETON, $scope);
    }

    public function testDetectsScopedAttribute(): void
    {
        $scope = $this->scanner->getScopeFromAttributes(ScopedClass::class);
        $this->assertSame(Scope::SCOPED, $scope);
    }

    public function testDetectsTransientAttribute(): void
    {
        $scope = $this->scanner->getScopeFromAttributes(TransientClass::class);
        $this->assertSame(Scope::TRANSIENT, $scope);
    }

    public function testReturnsNullForNoAttribute(): void
    {
        $scope = $this->scanner->getScopeFromAttributes(NoAttributeClass::class);
        $this->assertNull($scope);
    }
}

#[Singleton]
class SingletonClass {}

#[Scoped]
class ScopedClass {}

#[Transient]
class TransientClass {}

class NoAttributeClass {}
