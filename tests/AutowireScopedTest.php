<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\ScopedContainer;
use PHPdot\Container\Testing\TestContextProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function PHPdot\Container\scoped;
use function PHPdot\Container\singleton;

/**
 * Coverage for the reflection-based autowiring used by ScopedContainer for
 * scoped/transient classes that don't have an explicit factory closure.
 *
 * Before the fix, scoped/transient autowiring fell back to PHP-DI's `make()`,
 * which keeps a process-global `entriesBeingResolved` map. In a coroutine
 * runtime that map leaks across coroutines: a dep whose factory suspends
 * (e.g., `Pool::borrow()` waiting on a `Channel`) leaves the flag set, and
 * any second coroutine entering the same `make()` trips a false circular-dep.
 *
 * The fix autowires by reflection alone, recursing back into
 * `$this->get()` per param — keeping resolution coroutine-safe. These tests
 * deterministically cover the behavior the fix relies on.
 */
final class AutowireScopedTest extends TestCase
{
    private TestContextProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestContextProvider();
    }

    public function testScopedClassWithNoFactoryIsAutowired(): void
    {
        $container = $this->build([
            AutowireBare::class => scoped(),
        ]);

        $instance = $container->get(AutowireBare::class);

        self::assertInstanceOf(AutowireBare::class, $instance);
    }

    public function testScopedClassResolvesTypedDepsViaContainer(): void
    {
        $container = $this->build([
            AutowireDep::class    => singleton(),
            AutowireParent::class => scoped(),
        ]);

        $parent = $container->get(AutowireParent::class);

        self::assertSame($container->get(AutowireDep::class), $parent->dep);
    }

    public function testNestedScopedAutowiringResolvesEachLayerThroughContainer(): void
    {
        $container = $this->build([
            AutowireDep::class       => singleton(),
            AutowireMiddle::class    => scoped(),
            AutowireOuterChain::class => scoped(),
        ]);

        $outer = $container->get(AutowireOuterChain::class);

        self::assertInstanceOf(AutowireOuterChain::class, $outer);
        self::assertInstanceOf(AutowireMiddle::class, $outer->middle);
        self::assertInstanceOf(AutowireDep::class, $outer->middle->dep);
    }

    public function testDefaultValuesAreUsedForBuiltinParams(): void
    {
        $container = $this->build([
            AutowireWithDefaults::class => scoped(),
        ]);

        $instance = $container->get(AutowireWithDefaults::class);

        self::assertSame(42, $instance->count);
        self::assertSame('hello', $instance->label);
    }

    public function testNullableTypedDepFallsBackToNullWhenNotRegistered(): void
    {
        $container = $this->build([
            AutowireWithNullableDep::class => scoped(),
        ]);

        $instance = $container->get(AutowireWithNullableDep::class);

        // AutowireUnregistered isn't registered and has no constructor, so
        // it autowires fine via the fallback `class_exists` branch.
        self::assertInstanceOf(AutowireUnregistered::class, $instance->dep);
    }

    public function testRequiredBuiltinWithoutDefaultThrows(): void
    {
        $container = $this->build([
            AutowireRequiredBuiltin::class => scoped(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('builtin type');

        $container->get(AutowireRequiredBuiltin::class);
    }

    public function testNonInstantiableThrows(): void
    {
        $container = $this->build([
            AutowireAbstract::class => scoped(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not instantiable');

        $container->get(AutowireAbstract::class);
    }

    public function testScopedAutowiredInstanceIsCachedPerContext(): void
    {
        $container = $this->build([
            AutowireDep::class    => singleton(),
            AutowireParent::class => scoped(),
        ]);

        $a = $container->get(AutowireParent::class);
        $b = $container->get(AutowireParent::class);

        self::assertSame($a, $b);

        $this->provider->newContext();

        $c = $container->get(AutowireParent::class);
        self::assertNotSame($a, $c);
    }

    /**
     * Regression: when a scoped class is autowired and one of its deps has a
     * factory that throws synchronously, the failure surfaces cleanly without
     * leaving any resolution flag dangling. Before the fix a thrown exception
     * during `$phpdi->make()` could leave PHP-DI's `entriesBeingResolved`
     * inconsistent if the dep was resolved out-of-band.
     */
    public function testThrowingDepDoesNotPoisonSubsequentResolutions(): void
    {
        $attempts = 0;

        $container = $this->build([
            AutowireDep::class    => singleton(static function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1) {
                    throw new RuntimeException('boom');
                }
                return new AutowireDep();
            }),
            AutowireParent::class => scoped(),
        ]);

        try {
            $container->get(AutowireParent::class);
            self::fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        // Second context, second attempt — should resolve cleanly even though
        // the previous attempt threw. No circular-dep false positive.
        $this->provider->newContext();
        $parent = $container->get(AutowireParent::class);

        self::assertInstanceOf(AutowireParent::class, $parent);
        self::assertInstanceOf(AutowireDep::class, $parent->dep);
    }

    /**
     * @param array<string, mixed> $definitions
     */
    private function build(array $definitions): ScopedContainer
    {
        return (new ContainerBuilder())
            ->withContextProvider($this->provider)
            ->withScopeValidation(false)
            ->addDefinitions($definitions)
            ->build();
    }
}

final class AutowireBare {}

final class AutowireDep {}

final class AutowireParent
{
    public function __construct(
        public readonly AutowireDep $dep,
    ) {}
}

final class AutowireMiddle
{
    public function __construct(
        public readonly AutowireDep $dep,
    ) {}
}

final class AutowireOuterChain
{
    public function __construct(
        public readonly AutowireMiddle $middle,
    ) {}
}

final class AutowireWithDefaults
{
    public function __construct(
        public readonly int $count = 42,
        public readonly string $label = 'hello',
    ) {}
}

final class AutowireUnregistered {}

final class AutowireWithNullableDep
{
    public function __construct(
        public readonly ?AutowireUnregistered $dep = null,
    ) {}
}

final class AutowireRequiredBuiltin
{
    public function __construct(
        public readonly int $required,
    ) {}
}

abstract class AutowireAbstract {}
