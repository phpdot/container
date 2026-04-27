<?php

declare(strict_types=1);

/**
 * Array Context
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Context;

use PHPdot\Contracts\Container\ContextInterface;

final class ArrayContext implements ContextInterface
{
    /** @var array<string, object> */
    private array $instances = [];

    public function has(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    public function get(string $id): object|null
    {
        return $this->instances[$id] ?? null;
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function unset(string $id): void
    {
        unset($this->instances[$id]);
    }

    public function reset(): void
    {
        $this->instances = [];
    }
}
