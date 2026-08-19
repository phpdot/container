<?php

declare(strict_types=1);

/**
 * Base exception for the container package.
 *
 * Implements PSR-11's ContainerExceptionInterface so that every failure the
 * container raises — resolution, autowiring, definition loading, scope
 * validation — is catchable both as the package hierarchy and as the
 * interface PSR-11 §3 requires from a ContainerInterface implementation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
