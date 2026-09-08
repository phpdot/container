<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\Attribute\Inject;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

interface InjectedThing {}

final class DefaultThing implements InjectedThing {}

final class NamedThing implements InjectedThing {}

final class NamedInjectConsumer
{
    public function __construct(
        #[Inject('named.thing')]
        public readonly InjectedThing $thing,
    ) {}
}

final class TypedInjectConsumer
{
    public function __construct(
        public readonly InjectedThing $thing,
    ) {}
}

final class InjectAttributeTest extends TestCase
{
    #[Test]
    public function injectAttributeResolvesNamedDefinitionInsteadOfType(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            InjectedThing::class => new ScopedDefinition(Scope::SINGLETON, DefaultThing::class),
            'named.thing' => new ScopedDefinition(Scope::SINGLETON, NamedThing::class),
            NamedInjectConsumer::class => new ScopedDefinition(Scope::SCOPED),
        ]);

        $container = $builder->build();
        $consumer = $container->get(NamedInjectConsumer::class);

        self::assertInstanceOf(NamedThing::class, $consumer->thing);
        self::assertNotInstanceOf(DefaultThing::class, $consumer->thing);
    }

    #[Test]
    public function parameterWithoutInjectAttributeStillAutowiresByType(): void
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            InjectedThing::class => new ScopedDefinition(Scope::SINGLETON, DefaultThing::class),
            'named.thing' => new ScopedDefinition(Scope::SINGLETON, NamedThing::class),
            TypedInjectConsumer::class => new ScopedDefinition(Scope::SCOPED),
        ]);

        $container = $builder->build();
        $consumer = $container->get(TypedInjectConsumer::class);

        self::assertInstanceOf(DefaultThing::class, $consumer->thing);
    }
}
