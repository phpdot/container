<?php

declare(strict_types=1);

namespace PHPdot\Container;

use PHPdot\Container\Definition\ScopedDefinition;

/**
 * Mark a definition as Singleton (cached forever).
 *
 * @param class-string|\Closure|null $implementation
 */
function singleton(string|\Closure|null $implementation = null): ScopedDefinition
{
    if ($implementation instanceof \Closure) {
        return new ScopedDefinition(Scope::SINGLETON, factory: $implementation);
    }

    return new ScopedDefinition(Scope::SINGLETON, implementation: $implementation);
}

/**
 * Mark a definition as Scoped (cached per context/request).
 *
 * @param class-string|\Closure|null $implementation
 */
function scoped(string|\Closure|null $implementation = null): ScopedDefinition
{
    if ($implementation instanceof \Closure) {
        return new ScopedDefinition(Scope::SCOPED, factory: $implementation);
    }

    return new ScopedDefinition(Scope::SCOPED, implementation: $implementation);
}

/**
 * Mark a definition as Transient (always new).
 *
 * @param class-string|\Closure|null $implementation
 */
function transient(string|\Closure|null $implementation = null): ScopedDefinition
{
    if ($implementation instanceof \Closure) {
        return new ScopedDefinition(Scope::TRANSIENT, factory: $implementation);
    }

    return new ScopedDefinition(Scope::TRANSIENT, implementation: $implementation);
}
