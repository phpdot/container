<?php

declare(strict_types=1);

/**
 * Array Context
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Context;

use Closure;
use PHPdot\Contracts\Container\ContextDestroyInterface;
use PHPdot\Contracts\Container\ContextInterface;
use Throwable;

final class ArrayContext implements ContextInterface, ContextDestroyInterface
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var list<Closure(): void> */
    private array $destroyCallbacks = [];

    public function has(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    public function get(string $id): object|null
    {
        return $this->instances[$id] ?? null;
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function unset(string $id): void
    {
        unset($this->instances[$id]);
    }

    public function onDestroy(Closure $callback): void
    {
        $this->destroyCallbacks[] = $callback;
    }

    public function reset(): void
    {
        // LIFO — last registered fires first, matching Coroutine::defer semantics
        foreach (array_reverse($this->destroyCallbacks) as $callback) {
            try {
                $callback();
            } catch (Throwable) {
                // Destroy callbacks must not propagate
            }
        }
        $this->destroyCallbacks = [];
        $this->instances = [];
    }
}
