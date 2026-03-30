<?php

declare(strict_types=1);

/**
 * Scoped Container
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container;

use Closure;
use DI\Container;
use PHPdot\Container\Context\ContextProviderInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ScopedContainer implements ContainerInterface
{
    /** @var array<string, true> */
    private array $scopedIds = [];

    /** @var array<string, true> */
    private array $transientIds = [];

    /** @var array<string, Closure|null> Factories for scoped/transient */
    private array $factories = [];

    private Container $phpdi;

    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
    ) {}

    /**
     * Set the underlying PHP-DI container. Called by ContainerBuilder after build.
     */
    public function setPhpDi(Container $phpdi): void
    {
        $this->phpdi = $phpdi;
    }

    /**
     * @param class-string|null $implementation
     */
    public function registerScoped(string $id, Closure|null $factory = null, string|null $implementation = null): void
    {
        $this->scopedIds[$id] = true;
        $this->factories[$id] = $factory;
    }

    public function registerTransient(string $id, Closure|null $factory = null): void
    {
        $this->transientIds[$id] = true;
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->scopedIds[$id])) {
            return $this->getScoped($id);
        }

        if (isset($this->transientIds[$id])) {
            return $this->resolve($id);
        }

        return $this->phpdi->get($id);
    }

    public function has(string $id): bool
    {
        return isset($this->scopedIds[$id])
            || isset($this->transientIds[$id])
            || $this->phpdi->has($id);
    }

    /**
     * @param array<mixed> $parameters
     */
    public function make(string $name, array $parameters = []): mixed
    {
        // Scoped: return from context (same instance within scope)
        if (isset($this->scopedIds[$name])) {
            return $this->getScoped($name);
        }

        // Transient: always fresh via factory
        if (isset($this->transientIds[$name])) {
            return $this->resolve($name);
        }

        return $this->phpdi->make($name, $parameters);
    }

    /**
     * @param mixed $callable
     * @param array<mixed> $parameters
     */
    public function call(mixed $callable, array $parameters = []): mixed
    {
        /** @var callable $callable */
        return $this->phpdi->call($callable, $parameters);
    }

    /**
     * Get the underlying PHP-DI container.
     */
    public function phpdi(): Container
    {
        return $this->phpdi;
    }

    private function getScoped(string $id): object
    {
        $ctx = $this->contextProvider->getContext();

        if ($ctx->has($id)) {
            /** @var object */
            return $ctx->get($id);
        }

        $instance = $this->resolve($id);
        $ctx->set($id, $instance);

        return $instance;
    }

    private function resolve(string $id): object
    {
        $factory = $this->factories[$id] ?? null;

        if ($factory !== null) {
            $instance = $factory($this);
        } else {
            $instance = $this->phpdi->make($id);
        }

        if (!is_object($instance)) {
            throw new RuntimeException("Resolution for '{$id}' must return an object.");
        }

        return $instance;
    }
}
