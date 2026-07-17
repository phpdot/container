<?php

declare(strict_types=1);

/**
 * Context Resetter
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container;

use Closure;
use PHPdot\Contracts\Container\ContextProviderInterface;
use Throwable;

final class ContextResetter
{
    /**
     * @var list<Closure>
     */
    private array $destroyCallbacks = [];

    /**
     * Create a resetter bound to the active context provider.
     *
     * @param ContextProviderInterface $contextProvider
     */
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
    ) {}

    /**
     * Register a destroy callback.
     *
     * @param Closure(object): void $callback
     *
     * @return void
     */
    public function onDestroy(Closure $callback): void
    {
        $this->destroyCallbacks[] = $callback;
    }

    /**
     * Reset the current context. Calls destroy callbacks first; their
     * failures are swallowed — destroy is best-effort and must never
     * prevent the reset.
     *
     * @return void
     */
    public function reset(): void
    {
        $ctx = $this->contextProvider->getContext();

        foreach ($this->destroyCallbacks as $callback) {
            try {
                $callback($ctx);
            } catch (Throwable) {
            }
        }

        $ctx->reset();
    }
}
