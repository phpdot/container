<?php

declare(strict_types=1);

/**
 * Test Context Provider
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Testing;

use PHPdot\Container\Context\ArrayContext;
use PHPdot\Contracts\Container\ContextInterface;
use PHPdot\Contracts\Container\ContextProviderInterface;

final class TestContextProvider implements ContextProviderInterface
{
    /**
     * @var array<string, ContextInterface>
     */
    private array $contexts = [];

    private string $current = 'default';

    /**
     * Get context.
     *
     * @return ContextInterface
     */
    public function getContext(): ContextInterface
    {
        return $this->contexts[$this->current] ??= new ArrayContext();
    }

    /**
     * Simulate switching to a new context (new request).
     *
     * @param string|null $name
     *
     * @return void
     */
    public function newContext(string|null $name = null): void
    {
        $this->current = $name ?? uniqid('ctx_');
        $this->contexts[$this->current] = new ArrayContext();
    }

    /**
     * Reset all contexts.
     *
     * @return void
     */
    public function resetAll(): void
    {
        $this->contexts = [];
        $this->current = 'default';
    }
}
