<?php

declare(strict_types=1);

/**
 * Fluent chain: when() -> needs() -> provide().
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container;

use Closure;
use LogicException;
use Psr\Container\ContainerInterface;

final class ContextualBindingBuilder
{
    private string $abstract = '';

    /**
     * Create the builder step for binding a consumer to its contextual dependency.
     *
     * @param ContainerBuilder $builder
     * @param string $consumer
     */
    public function __construct(
        private readonly ContainerBuilder $builder,
        private readonly string $consumer,
    ) {}

    /**
     * Needs.
     *
     * @param string $abstract
     *
     * @return self
     */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    /**
     * Complete the binding: the consumer receives this concrete for the needed abstract.
     *
     * @param class-string|Closure(ContainerInterface): mixed $concrete
     *
     * @return void
     */
    public function provide(string|Closure $concrete): void
    {
        if ($this->abstract === '') {
            throw new LogicException(
                'Call needs() before provide()',
            );
        }

        $this->builder->addContextualBinding(
            $this->consumer,
            $this->abstract,
            $concrete,
        );
    }
}
