<?php

declare(strict_types=1);

/**
 * Fluent builder that auto-registers the definition in the ContainerBuilder
 * when a scope method (singleton/scoped/transient) is called.
 *
 * Usage:
 * $builder->add(Router::class)->singleton();
 * $builder->add(CacheInterface::class, RedisCache::class)->singleton();
 * $builder->add(Connection::class, fn() => new Connection(...))->scoped();
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container;

use Closure;
use PHPdot\Container\Definition\ScopedDefinition;

final class RegisteringDefinitionBuilder
{
    private Closure|null $onDestroy = null;

    /**
     * Create the fluent registration step for one service id.
     *
     * @param ContainerBuilder $containerBuilder The parent builder to register with
     * @param string $id The service identifier
     * @param class-string|null $implementation Concrete class to use
     * @param Closure|null $factory Factory closure to create the instance
     */
    public function __construct(
        private readonly ContainerBuilder $containerBuilder,
        private readonly string $id,
        private readonly string|null $implementation,
        private readonly Closure|null $factory,
    ) {}

    /**
     * Register as a singleton (one instance per worker).
     *
     * @return ScopedDefinition
     */
    public function singleton(): ScopedDefinition
    {
        $definition = new ScopedDefinition(Scope::SINGLETON, $this->implementation, $this->factory, $this->onDestroy);
        $this->containerBuilder->register($this->id, $definition);

        return $definition;
    }

    /**
     * Register as scoped (one instance per coroutine/request).
     *
     * @return ScopedDefinition
     */
    public function scoped(): ScopedDefinition
    {
        $definition = new ScopedDefinition(Scope::SCOPED, $this->implementation, $this->factory, $this->onDestroy);
        $this->containerBuilder->register($this->id, $definition);

        return $definition;
    }

    /**
     * Register as transient (new instance every time).
     *
     * @return ScopedDefinition
     */
    public function transient(): ScopedDefinition
    {
        $definition = new ScopedDefinition(Scope::TRANSIENT, $this->implementation, $this->factory, $this->onDestroy);
        $this->containerBuilder->register($this->id, $definition);

        return $definition;
    }

    /**
     * Add a destroy callback (called when scoped instance is released).
     *
     * @param Closure(object): void $callback
     *
     * @return RegisteringDefinitionBuilder
     */
    public function onDestroy(Closure $callback): self
    {
        $this->onDestroy = $callback;

        return $this;
    }
}
