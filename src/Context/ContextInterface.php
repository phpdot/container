<?php

declare(strict_types=1);

/**
 * Context Interface
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Context;

interface ContextInterface
{
    public function has(string $id): bool;

    public function get(string $id): object|null;

    public function set(string $id, object $instance): void;

    public function unset(string $id): void;

    public function reset(): void;
}
