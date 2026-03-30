<?php

declare(strict_types=1);

/**
 * Context Provider Interface
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Context;

interface ContextProviderInterface
{
    /**
     * Returns the active context for the current execution unit.
     *
     * - FPM: the process (only one context ever exists)
     * - Swoole: the current coroutine
     * - Fiber: the current fiber
     */
    public function getContext(): ContextInterface;
}
