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
    /** @var array<string, ContextInterface> */
    private array $contexts = [];

    private string $current = 'default';

    public function getContext(): ContextInterface
    {
        return $this->contexts[$this->current] ??= new ArrayContext();
    }

    /**
     * Simulate switching to a new context (new request).
     */
    public function newContext(string|null $name = null): void
    {
        $this->current = $name ?? uniqid('ctx_');
        $this->contexts[$this->current] = new ArrayContext();
    }

    /**
     * Reset all contexts.
     */
    public function resetAll(): void
    {
        $this->contexts = [];
        $this->current = 'default';
    }
}
