<?php

declare(strict_types=1);

/**
 * Transient Attribute
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Transient {}
