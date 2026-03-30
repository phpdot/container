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
use PHPdot\Container\Context\ContextProviderInterface;
use Throwable;

final class ContextResetter
{
    /** @var list<Closure> */
    private array $destroyCallbacks = [];

    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
    ) {}

    /**
     * Register a destroy callback.
     *
     * @param Closure(object): void $callback
     */
    public function onDestroy(Closure $callback): void
    {
        $this->destroyCallbacks[] = $callback;
    }

    /**
     * Reset the current context. Calls destroy callbacks first.
     */
    public function reset(): void
    {
        $ctx = $this->contextProvider->getContext();

        foreach ($this->destroyCallbacks as $callback) {
            try {
                $callback($ctx);
            } catch (Throwable) {
                // Destroy callbacks should not prevent reset
            }
        }

        $ctx->reset();
    }
}
