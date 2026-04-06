<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\Attribute\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Config('database')]
final class DatabaseConfig
{
}

#[Config('i18n')]
final class I18nTestConfig
{
}

final class ConfigAttributeTest extends TestCase
{
    #[Test]
    public function it_is_readable_via_reflection(): void
    {
        $ref = new \ReflectionClass(DatabaseConfig::class);
        $attrs = $ref->getAttributes(Config::class);

        self::assertCount(1, $attrs);
    }

    #[Test]
    public function it_stores_name(): void
    {
        $ref = new \ReflectionClass(DatabaseConfig::class);
        $attr = $ref->getAttributes(Config::class)[0]->newInstance();

        self::assertSame('database', $attr->name);
    }

    #[Test]
    public function it_stores_different_names(): void
    {
        $ref = new \ReflectionClass(I18nTestConfig::class);
        $attr = $ref->getAttributes(Config::class)[0]->newInstance();

        self::assertSame('i18n', $attr->name);
    }

    #[Test]
    public function it_targets_classes_only(): void
    {
        $ref = new \ReflectionClass(Config::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $flags = $attrs[0]->newInstance()->flags;

        self::assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
