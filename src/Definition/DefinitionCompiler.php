<?php

declare(strict_types=1);

/**
 * Definition Compiler
 *
 * Compiles a SINGLETON ScopedDefinition into a PHP-DI definition. Scoped and
 * transient definitions are handled directly by ScopedContainer and never
 * reach the compiler.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Definition;

use DI;

final class DefinitionCompiler
{
    public function compileSingleton(string $id, ScopedDefinition $definition): mixed
    {
        if ($definition->factory !== null) {
            return DI\factory($definition->factory);
        }

        if ($definition->implementation !== null) {
            return DI\autowire($definition->implementation);
        }

        return DI\autowire($id);
    }
}
