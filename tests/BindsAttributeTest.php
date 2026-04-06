<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\Attribute\Binds;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

interface CacheTestInterface
{
}

interface StoreTestInterface
{
}

#[Binds(CacheTestInterface::class)]
final class SingleBindClass implements CacheTestInterface
{
}

#[Binds(CacheTestInterface::class)]
#[Binds(StoreTestInterface::class)]
final class MultiBindClass implements CacheTestInterface, StoreTestInterface
{
}

final class BindsAttributeTest extends TestCase
{
    #[Test]
    public function it_is_readable_via_reflection(): void
    {
        $ref = new \ReflectionClass(SingleBindClass::class);
        $attrs = $ref->getAttributes(Binds::class);

        self::assertCount(1, $attrs);
    }

    #[Test]
    public function it_stores_interface(): void
    {
        $ref = new \ReflectionClass(SingleBindClass::class);
        $attr = $ref->getAttributes(Binds::class)[0]->newInstance();

        self::assertSame(CacheTestInterface::class, $attr->interface);
    }

    #[Test]
    public function it_supports_multiple_on_same_class(): void
    {
        $ref = new \ReflectionClass(MultiBindClass::class);
        $attrs = $ref->getAttributes(Binds::class);

        self::assertCount(2, $attrs);

        $first = $attrs[0]->newInstance();
        $second = $attrs[1]->newInstance();

        self::assertSame(CacheTestInterface::class, $first->interface);
        self::assertSame(StoreTestInterface::class, $second->interface);
    }

    #[Test]
    public function it_targets_classes_only(): void
    {
        $ref = new \ReflectionClass(Binds::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $flags = $attrs[0]->newInstance()->flags;

        self::assertSame(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE, $flags);
    }
}
