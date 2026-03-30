<?php

declare(strict_types=1);

/**
 * Scope Mismatch Exception
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Validation;

use PHPdot\Container\Scope;
use RuntimeException;

final class ScopeMismatchException extends RuntimeException
{
    public function __construct(
        string $serviceId,
        Scope $serviceScope,
        string $dependencyId,
        Scope $dependencyScope,
    ) {
        $message = sprintf(
            "Service \"%s\" is registered as %s but depends on \"%s\" which is %s.\n\n"
            . "A %s service cannot depend on a %s service because the %s outlives the scope boundary.\n\n"
            . "Solutions:\n"
            . "  - Change \"%s\" to %s\n"
            . "  - Inject DI\\FactoryInterface instead and resolve at call-time\n",
            $serviceId,
            $serviceScope->value,
            $dependencyId,
            $dependencyScope->value,
            $serviceScope->value,
            $dependencyScope->value,
            $serviceScope->value,
            $serviceId,
            $dependencyScope->value,
        );

        parent::__construct($message);
    }
}
