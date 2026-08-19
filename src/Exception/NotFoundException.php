<?php

declare(strict_types=1);

/**
 * A requested entry cannot be resolved because its target does not exist.
 *
 * The PSR-11 NotFoundExceptionInterface case: the identifier names a class
 * the autoloader cannot produce, so no amount of wiring could satisfy the
 * get() call.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}
