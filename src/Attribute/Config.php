<?php

declare(strict_types=1);

/**
 * Marks a class as configured from a named config file.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Config
{
    /**
     * Point the attributed class at its config file.
     *
     * @param string $name Config file name (without .php extension)
     */
    public function __construct(
        public readonly string $name,
    ) {}
}
