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
use Psr\Container\ContainerInterface;

final class ScopedDefinition
{
    /**
     * Create a definition carrying its scope and construction strategy.
     *
     * The onDestroy callback fires at coroutine end in Swoole or reset() in
     * FPM/CLI, when the active context implements ContextDestroyInterface.
     *
     * @param class-string|null $implementation
     * @param Closure|null $factory
     * @param Closure(object, ContainerInterface): void|null $onDestroy Fires when the context ends.
     * @param Scope $scope
     */
    public function __construct(
        public readonly Scope $scope,
        public readonly string|null $implementation = null,
        public readonly Closure|null $factory = null,
        public readonly Closure|null $onDestroy = null,
    ) {}

    /**
     * A copy of this definition with the destroy callback attached.
     *
     * @param Closure(object, ContainerInterface): void $callback
     *
     * @return self
     */
    public function withOnDestroy(Closure $callback): self
    {
        return new self($this->scope, $this->implementation, $this->factory, $callback);
    }
}
