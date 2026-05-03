<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use Closure;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Context\ArrayContext;
use PHPdot\Container\Context\ArrayContextProvider;
use PHPdot\Container\ScopedContainer;
use PHPdot\Container\Testing\TestContextProvider;
use PHPdot\Contracts\Container\ContextDestroyInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

use function PHPdot\Container\scoped;
use function PHPdot\Container\singleton;

/**
 * Coverage for the per-instance onDestroy lifecycle.
 *
 * When a ScopedDefinition has both an `$onDestroy` callback AND the active
 * context implements ContextDestroyInterface, the container arms the callback
 * at the moment the instance is cached. The callback fires when the context
 * is destroyed (coroutine end in Swoole, reset() in FPM/CLI).
 */
final class OnDestroyTest extends TestCase
{
    public function testArrayContextImplementsContextDestroyInterface(): void
    {
        $ctx = new ArrayContext();
        self::assertInstanceOf(ContextDestroyInterface::class, $ctx);
    }

    public function testOnDestroyCallbackFiresOnContextReset(): void
    {
        $fired = [];
        $ctx = new ArrayContext();
        $ctx->onDestroy(static function () use (&$fired): void {
            $fired[] = 'one';
        });
        $ctx->onDestroy(static function () use (&$fired): void {
            $fired[] = 'two';
        });

        $ctx->reset();

        // LIFO order — last registered fires first
        self::assertSame(['two', 'one'], $fired);
    }

    public function testOnDestroyCallbacksClearedAfterReset(): void
    {
        $count = 0;
        $ctx = new ArrayContext();
        $ctx->onDestroy(static function () use (&$count): void {
            $count++;
        });

        $ctx->reset();
        $ctx->reset();

        self::assertSame(1, $count);
    }

    public function testThrowingDestroyCallbackDoesNotPreventOthers(): void
    {
        $fired = [];
        $ctx = new ArrayContext();
        $ctx->onDestroy(static function () use (&$fired): void {
            $fired[] = 'after';
        });
        $ctx->onDestroy(static function (): void {
            throw new \RuntimeException('boom');
        });
        $ctx->onDestroy(static function () use (&$fired): void {
            $fired[] = 'before';
        });

        $ctx->reset();

        // LIFO: 'before' (last) → throws → 'after' (first registered)
        self::assertSame(['before', 'after'], $fired);
    }

    public function testScopedFactoryWithOnDestroyFiresOnContextReset(): void
    {
        $released = [];

        $provider = new ArrayContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($provider)
            ->withScopeValidation(false)
            ->addDefinitions([
                'pool' => singleton(static function (): array {
                    return ['borrowed' => []];
                }),
                stdClass::class => scoped(
                    static fn (ContainerInterface $c): stdClass => new stdClass(),
                    onDestroy: static function (object $instance, ContainerInterface $c) use (&$released): void {
                        $released[] = spl_object_id($instance);
                    },
                ),
            ])
            ->build();

        $a = $container->get(stdClass::class);
        $aId = spl_object_id($a);

        // Same context — no destroy yet
        self::assertSame([], $released);

        $provider->getContext()->reset();

        self::assertSame([$aId], $released);
    }

    public function testOnDestroyArmedOncePerContextEvenWithRepeatedGets(): void
    {
        $callCount = 0;

        $provider = new ArrayContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($provider)
            ->withScopeValidation(false)
            ->addDefinitions([
                stdClass::class => scoped(
                    static fn (): stdClass => new stdClass(),
                    onDestroy: static function () use (&$callCount): void {
                        $callCount++;
                    },
                ),
            ])
            ->build();

        $a = $container->get(stdClass::class);
        $b = $container->get(stdClass::class);
        $c = $container->get(stdClass::class);

        // Same instance from cache
        self::assertSame($a, $b);
        self::assertSame($a, $c);

        $provider->getContext()->reset();

        // Destroy fires once even though get() was called three times
        self::assertSame(1, $callCount);
    }

    public function testOnDestroyReceivesInstanceAndContainer(): void
    {
        $captured = null;

        $provider = new ArrayContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($provider)
            ->withScopeValidation(false)
            ->addDefinitions([
                stdClass::class => scoped(
                    static fn (): stdClass => new stdClass(),
                    onDestroy: static function (object $instance, ContainerInterface $c) use (&$captured): void {
                        $captured = ['instance' => $instance, 'container' => $c];
                    },
                ),
            ])
            ->build();

        $resolved = $container->get(stdClass::class);
        $provider->getContext()->reset();

        self::assertNotNull($captured);
        self::assertSame($resolved, $captured['instance']);
        self::assertInstanceOf(ContainerInterface::class, $captured['container']);
    }

    public function testNoOnDestroyMeansNoCallback(): void
    {
        // Smoke test: scoped binding without onDestroy still works as before
        $provider = new ArrayContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($provider)
            ->withScopeValidation(false)
            ->addDefinitions([
                stdClass::class => scoped(static fn (): stdClass => new stdClass()),
            ])
            ->build();

        $instance = $container->get(stdClass::class);
        $provider->getContext()->reset();

        self::assertInstanceOf(stdClass::class, $instance);
    }

    public function testOnDestroyNotArmedWhenFactoryThrows(): void
    {
        $destroyCalled = false;

        $provider = new ArrayContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($provider)
            ->withScopeValidation(false)
            ->addDefinitions([
                stdClass::class => scoped(
                    static function (): stdClass {
                        throw new \RuntimeException('factory failed');
                    },
                    onDestroy: static function () use (&$destroyCalled): void {
                        $destroyCalled = true;
                    },
                ),
            ])
            ->build();

        try {
            $container->get(stdClass::class);
            self::fail('Expected exception not thrown');
        } catch (\RuntimeException $e) {
            self::assertSame('factory failed', $e->getMessage());
        }

        $provider->getContext()->reset();

        self::assertFalse($destroyCalled);
    }

    public function testDestroyFiresPerContextNotGlobally(): void
    {
        $fired = [];

        $provider = new TestContextProvider();
        $container = (new ContainerBuilder())
            ->withContextProvider($provider)
            ->withScopeValidation(false)
            ->addDefinitions([
                stdClass::class => scoped(
                    static fn (): stdClass => new stdClass(),
                    onDestroy: static function (object $instance) use (&$fired): void {
                        $fired[] = spl_object_id($instance);
                    },
                ),
            ])
            ->build();

        $first = $container->get(stdClass::class);
        $firstId = spl_object_id($first);

        // Reset context A (fires its destroy callbacks)
        $provider->getContext()->reset();

        self::assertSame([$firstId], $fired);

        // Switch to a fresh context — fresh instance, fresh destroy registration
        $provider->newContext();
        $second = $container->get(stdClass::class);
        $secondId = spl_object_id($second);

        self::assertNotSame($first, $second);

        $provider->getContext()->reset();

        self::assertSame([$firstId, $secondId], $fired);
    }
}
