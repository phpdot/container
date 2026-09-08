<?php

declare(strict_types=1);

/**
 * Resolves a constructor parameter from a named container definition
 * instead of type-based autowiring.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    /**
     * Point the attributed parameter at a named container entry.
     *
     * @param string $name Container entry id the parameter resolves to
     */
    public function __construct(
        public string $name,
    ) {}
}
