<?php

declare(strict_types=1);

namespace PHPdot\Container;

use Closure;
use Composer\Autoload\ClassLoader;
use PHPdot\Container\Definition\ScopedDefinition;
use RuntimeException;

/**
 * Mark a definition as Singleton (cached forever).
 *
 * @param class-string|Closure|null $implementation
 */
function singleton(string|Closure|null $implementation = null): ScopedDefinition
{
    if ($implementation instanceof Closure) {
        return new ScopedDefinition(Scope::SINGLETON, factory: $implementation);
    }

    return new ScopedDefinition(Scope::SINGLETON, implementation: $implementation);
}

/**
 * Mark a definition as Scoped (cached per context/request).
 *
 * @param class-string|Closure|null $implementation
 * @param Closure(object, \Psr\Container\ContainerInterface): void|null $onDestroy
 *     Optional callback fired when the context ends (coroutine end in Swoole,
 *     reset() in FPM/CLI). Receives the cached instance and the container.
 */
function scoped(
    string|Closure|null $implementation = null,
    Closure|null $onDestroy = null,
): ScopedDefinition {
    if ($implementation instanceof Closure) {
        return new ScopedDefinition(Scope::SCOPED, factory: $implementation, onDestroy: $onDestroy);
    }

    return new ScopedDefinition(Scope::SCOPED, implementation: $implementation, onDestroy: $onDestroy);
}

/**
 * Mark a definition as Transient (always new).
 *
 * @param class-string|Closure|null $implementation
 */
function transient(string|Closure|null $implementation = null): ScopedDefinition
{
    if ($implementation instanceof Closure) {
        return new ScopedDefinition(Scope::TRANSIENT, factory: $implementation);
    }

    return new ScopedDefinition(Scope::TRANSIENT, implementation: $implementation);
}

/**
 * Resolve the absolute path to the Composer vendor directory, optionally
 * joined with a relative segment.
 *
 * Uses Composer's documented runtime API — no path arithmetic, no environment
 * guessing. Pass nothing for the vendor dir itself; pass a relative path to
 * get the joined absolute path.
 *
 * @throws RuntimeException If no Composer autoloader is registered.
 */
function vendor(string $relative = ''): string
{
    $loaders = ClassLoader::getRegisteredLoaders();

    if ($loaders === []) {
        throw new RuntimeException('No Composer autoloader registered.');
    }

    $vendorDir = (string) array_key_first($loaders);

    return $relative === ''
        ? $vendorDir
        : $vendorDir . '/' . ltrim($relative, '/');
}
