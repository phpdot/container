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

final class CallbackContextProvider implements ContextProviderInterface
{
    /** @var Closure(): ContextInterface */
    private Closure $callback;

    /**
     * @param Closure(): ContextInterface $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    public function getContext(): ContextInterface
    {
        return ($this->callback)();
    }
}
