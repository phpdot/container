<?php

declare(strict_types=1);

/**
 * Wraps ScopedContainer with per-consumer binding overrides.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container;

use Closure;
use DI\FactoryInterface;
use Psr\Container\ContainerInterface;

final readonly class ContextualContainer implements ContainerInterface, FactoryInterface
{
    /**
     * Create a consumer-specific view over the scoped container.
     *
     * @param ScopedContainer $inner
     * @param array<string, string|Closure> $bindings
     */
    public function __construct(
        private ScopedContainer $inner,
        private array $bindings,
    ) {}

    /**
     * Resolve an entry, honoring the consumer contextual bindings first.
     *
     * @param string $id
     *
     * @return mixed
     */
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

    /**
     * Whether the entry is resolvable through this view.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || $this->inner->has($id);
    }

    /**
     * Create a fresh instance by name via the inner container.
     *
     * @param array<mixed> $parameters
     * @param string $name
     *
     * @return mixed
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->inner->make($name, $parameters);
    }

    /**
     * Invoke a callable with dependencies resolved by the inner container.
     *
     * @param mixed $callable
     * @param array<mixed> $parameters
     *
     * @return mixed
     */
    public function call(mixed $callable, array $parameters = []): mixed
    {
        return $this->inner->call($callable, $parameters);
    }
}
