<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;
use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;
use PHPdot\Container\Validation\ScopeMismatchException;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testSingletonDependingOnScopedThrows(): void
    {
        $this->expectException(ScopeMismatchException::class);

        (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->withScopeValidation(true)
            ->addDefinitions([
                SingletonService::class => singleton(),
                ScopedDependency::class => scoped(),
            ])
            ->build();
    }

    public function testScopedDependingOnSingletonAllowed(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->withScopeValidation(true)
            ->addDefinitions([
                ScopedWithSingletonDep::class => scoped(),
                SingletonDep::class => singleton(),
            ])
            ->build();

        $this->assertNotNull($container);
    }

    public function testValidationCanBeDisabled(): void
    {
        // Should not throw even with invalid dependencies
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->withScopeValidation(false)
            ->addDefinitions([
                SingletonService::class => singleton(),
                ScopedDependency::class => scoped(),
            ])
            ->build();

        $this->assertNotNull($container);
    }
}

// Test classes for validation

class ScopedDependency {}

class SingletonService
{
    public function __construct(public readonly ScopedDependency $dep) {}
}

class SingletonDep {}

class ScopedWithSingletonDep
{
    public function __construct(public readonly SingletonDep $dep) {}
}
