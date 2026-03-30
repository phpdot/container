<?php

declare(strict_types=1);

/**
 * Scoped Definition
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Definition;

use Closure;
use PHPdot\Container\Scope;

final class ScopedDefinition
{
    /**
     * @param class-string|null $implementation
     * @param Closure|null $factory
     * @param Closure|null $onDestroy
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly string|null $implementation = null,
        public readonly Closure|null $factory = null,
        public readonly Closure|null $onDestroy = null,
    ) {}

    /**
     * @param Closure(object): void $callback
     */
    public function withOnDestroy(Closure $callback): self
    {
        return new self($this->scope, $this->implementation, $this->factory, $callback);
    }
}
