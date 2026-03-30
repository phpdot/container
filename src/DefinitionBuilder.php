<?php

declare(strict_types=1);

/**
 * Definition Builder
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container;

use Closure;
use PHPdot\Container\Definition\ScopedDefinition;

final class DefinitionBuilder
{
    /** @var class-string|null */
    private string|null $implementation;
    private Closure|null $factory;
    private Closure|null $onDestroy = null;

    /**
     * @param class-string|Closure|null $implementation
     */
    public function __construct(
        string|Closure|null $implementation = null,
    ) {
        if ($implementation instanceof Closure) {
            $this->implementation = null;
            $this->factory = $implementation;
        } else {
            $this->implementation = $implementation;
            $this->factory = null;
        }
    }

    public function singleton(): ScopedDefinition
    {
        return new ScopedDefinition(Scope::SINGLETON, $this->implementation, $this->factory, $this->onDestroy);
    }

    public function scoped(): ScopedDefinition
    {
        return new ScopedDefinition(Scope::SCOPED, $this->implementation, $this->factory, $this->onDestroy);
    }

    public function transient(): ScopedDefinition
    {
        return new ScopedDefinition(Scope::TRANSIENT, $this->implementation, $this->factory, $this->onDestroy);
    }

    /**
     * @param Closure(object): void $callback
     */
    public function onDestroy(Closure $callback): self
    {
        $this->onDestroy = $callback;

        return $this;
    }
}
