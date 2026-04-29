<?php

declare(strict_types=1);
namespace PHPdot\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Binds
{
    /**
     * @param class-string $interface The interface this class is the default for
     */
    public function __construct(
        public readonly string $interface,
    ) {}
}
