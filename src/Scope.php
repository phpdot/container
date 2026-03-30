<?php

declare(strict_types=1);

/**
 * Scope Enum
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container;

enum Scope: string
{
    case SINGLETON = 'singleton';
    case SCOPED = 'scoped';
    case TRANSIENT = 'transient';
}
