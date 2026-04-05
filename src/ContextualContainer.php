<?php

declare(strict_types=1);

namespace PHPdot\Container;

use Closure;
use DI\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Wraps ScopedContainer with per-consumer binding overrides.
 */
final readonly class ContextualContainer implements ContainerInterface, FactoryInterface
{
    /**
     * @param ScopedContainer $inner
     * @param array<string, string|Closure> $bindings
     */
    public function __construct(
        private ScopedContainer $inner,
        private array $bindings,
    ) {
    }

    public function get(string $id): mixed
    {
        $binding = $this->bindings[$id] ?? null;

        if ($binding === null) {
            return $this->inner->get($id);
        }

        if ($binding instanceof Closure) {
            return $binding($this->inner);
        }

        return $this->inner->get($binding);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || $this->inner->has($id);
    }

    /**
     * @param array<mixed> $parameters
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->inner->make($name, $parameters);
    }

    /**
     * @param mixed $callable
     * @param array<mixed> $parameters
     */
    public function call(mixed $callable, array $parameters = []): mixed
    {
        return $this->inner->call($callable, $parameters);
    }
}
