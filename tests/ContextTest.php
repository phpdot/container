<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\Context\ArrayContext;
use PHPdot\Container\Context\ArrayContextProvider;
use PHPdot\Container\Context\CallbackContextProvider;
use PHPdot\Container\Testing\TestContextProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContextTest extends TestCase
{
    // ─── ArrayContext ───

    #[Test]
    public function arrayContextSetAndGet(): void
    {
        $ctx = new ArrayContext();
        $obj = new stdClass();

        $ctx->set('key', $obj);

        $this->assertTrue($ctx->has('key'));
        $this->assertSame($obj, $ctx->get('key'));
    }

    #[Test]
    public function arrayContextGetReturnsNullForMissing(): void
    {
        $ctx = new ArrayContext();

        $this->assertFalse($ctx->has('key'));
        $this->assertNull($ctx->get('key'));
    }

    #[Test]
    public function arrayContextUnset(): void
    {
        $ctx = new ArrayContext();
        $ctx->set('key', new stdClass());

        $ctx->unset('key');

        $this->assertFalse($ctx->has('key'));
    }

    #[Test]
    public function arrayContextReset(): void
    {
        $ctx = new ArrayContext();
        $ctx->set('a', new stdClass());
        $ctx->set('b', new stdClass());

        $ctx->reset();

        $this->assertFalse($ctx->has('a'));
        $this->assertFalse($ctx->has('b'));
    }

    // ─── ArrayContextProvider ───

    #[Test]
    public function arrayContextProviderReturnsSameContext(): void
    {
        $provider = new ArrayContextProvider();

        $a = $provider->getContext();
        $b = $provider->getContext();

        $this->assertSame($a, $b);
    }

    // ─── CallbackContextProvider ───

    #[Test]
    public function callbackContextProvider(): void
    {
        $ctx = new ArrayContext();
        $provider = new CallbackContextProvider(fn() => $ctx);

        $this->assertSame($ctx, $provider->getContext());
    }

    // ─── TestContextProvider ───

    #[Test]
    public function contextProviderNewContext(): void
    {
        $provider = new TestContextProvider();

        $ctx1 = $provider->getContext();
        $ctx1->set('key', new stdClass());

        $provider->newContext();
        $ctx2 = $provider->getContext();

        $this->assertNotSame($ctx1, $ctx2);
        $this->assertFalse($ctx2->has('key'));
    }

    #[Test]
    public function contextProviderResetAll(): void
    {
        $provider = new TestContextProvider();

        $provider->getContext()->set('key', new stdClass());
        $provider->newContext('second');
        $provider->getContext()->set('key', new stdClass());

        $provider->resetAll();

        $this->assertFalse($provider->getContext()->has('key'));
    }
}
