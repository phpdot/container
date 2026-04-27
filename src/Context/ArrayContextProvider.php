<?php

declare(strict_types=1);

/**
 * Array Context Provider
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Context;

use PHPdot\Contracts\Container\ContextInterface;
use PHPdot\Contracts\Container\ContextProviderInterface;

final class ArrayContextProvider implements ContextProviderInterface
{
    private ArrayContext $context;

    public function __construct()
    {
        $this->context = new ArrayContext();
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }
}
