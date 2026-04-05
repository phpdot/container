<?php

declare(strict_types=1);

namespace PHPdot\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * Fluent chain: when() -> needs() -> provide().
 */
final class ContextualBindingBuilder
{
    private string $abstract = '';

    public function __construct(
        private readonly ContainerBuilder $builder,
        private readonly string $consumer,
    ) {
    }

    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    /**
     * @param class-string|Closure(ContainerInterface): mixed $concrete
     */
    public function provide(string|Closure $concrete): void
    {
        $this->builder->addContextualBinding(
            $this->consumer,
            $this->abstract,
            $concrete,
        );
    }
}
