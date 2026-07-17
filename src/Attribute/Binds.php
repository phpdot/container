<?php

declare(strict_types=1);

/**
 * Marks a class as the default implementation bound to an interface.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Binds
{
    /**
     * Bind the attributed class as the default implementation of the interface.
     *
     * @param class-string $interface The interface this class is the default for
     */
    public function __construct(
        public readonly string $interface,
    ) {}
}
