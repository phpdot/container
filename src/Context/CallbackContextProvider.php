<?php

declare(strict_types=1);

/**
 * Callback Context Provider
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Context;

use Closure;
use PHPdot\Contracts\Container\ContextInterface;
use PHPdot\Contracts\Container\ContextProviderInterface;

final class CallbackContextProvider implements ContextProviderInterface
{
    /**
     * @var Closure(): ContextInterface
     */
    private Closure $callback;

    /**
     * Create a provider that delegates context lookup to the callback.
     *
     * @param Closure(): ContextInterface $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * The context produced by the callback for the current execution.
     *
     * @return ContextInterface
     */
    public function getContext(): ContextInterface
    {
        return ($this->callback)();
    }
}
