<?php

declare(strict_types=1);
namespace PHPdot\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Config
{
    /**
     * @param string $name Config file name (without .php extension)
     */
    public function __construct(
        public readonly string $name,
    ) {}
}
