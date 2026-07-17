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
    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var list<Closure(): void>
     */
    private array $destroyCallbacks = [];

    /**
     * Has.
     *
     * @param string $id
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    /**
     * Get.
     *
     * @param string $id
     *
     * @return object|null
     */
    public function get(string $id): object|null
    {
        return $this->instances[$id] ?? null;
    }

    /**
     * Set.
     *
     * @param string $id
     * @param object $instance
     *
     * @return void
     */
    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Unset.
     *
     * @param string $id
     *
     * @return void
     */
    public function unset(string $id): void
    {
        unset($this->instances[$id]);
    }

    /**
     * On destroy.
     *
     * @param Closure $callback
     *
     * @return void
     */
    public function onDestroy(Closure $callback): void
    {
        $this->destroyCallbacks[] = $callback;
    }

    /**
     * Run destroy callbacks in LIFO order — last registered fires first,
     * matching Coroutine::defer semantics — then drop every instance.
     * Callback failures never propagate.
     *
     * @return void
     */
    public function reset(): void
    {
        foreach (array_reverse($this->destroyCallbacks) as $callback) {
            try {
                $callback();
            } catch (Throwable) {
            }
        }
        $this->destroyCallbacks = [];
        $this->instances = [];
    }
}
