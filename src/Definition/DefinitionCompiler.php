<?php

declare(strict_types=1);

/**
 * Definition Compiler
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Definition;

use DI;
use PHPdot\Container\Context\ContextProviderInterface;
use PHPdot\Container\Scope;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class DefinitionCompiler
{
    /**
     * Compile a set of definitions, transforming scoped entries into PHP-DI factories.
     *
     * @param array<string, mixed> $definitions Raw definitions (mix of ScopedDefinition and PHP-DI native)
     * @param Scope $defaultScope Scope for entries without explicit scope
     * @return array<string, mixed> PHP-DI compatible definitions
     */
    public function compile(array $definitions, Scope $defaultScope): array
    {
        $compiled = [];

        foreach ($definitions as $id => $definition) {
            if ($definition instanceof ScopedDefinition) {
                $compiled[$id] = $this->compileScopedDefinition($id, $definition);
            } elseif ($definition instanceof DI\Definition\Helper\DefinitionHelper) {
                // Raw PHP-DI definition — apply default scope
                $compiled[$id] = $this->applyDefaultScope($id, $definition, $defaultScope);
            } else {
                // Direct value — treat as singleton
                $compiled[$id] = $definition;
            }
        }

        return $compiled;
    }

    private function compileScopedDefinition(string $id, ScopedDefinition $definition): mixed
    {
        return match ($definition->scope) {
            Scope::SINGLETON => $this->compileSingleton($id, $definition),
            Scope::SCOPED => $this->compileScoped($id, $definition),
            Scope::TRANSIENT => $this->compileTransient($id, $definition),
        };
    }

    private function compileSingleton(string $id, ScopedDefinition $definition): mixed
    {
        if ($definition->factory !== null) {
            return DI\factory($definition->factory);
        }

        if ($definition->implementation !== null) {
            return DI\autowire($definition->implementation);
        }

        return DI\autowire($id);
    }

    private function compileScoped(string $id, ScopedDefinition $definition): mixed
    {
        $factory = $definition->factory;
        $implementation = $definition->implementation;

        return DI\factory(static function (ContainerInterface $c) use ($id, $factory, $implementation): object {
            /** @var ContextProviderInterface $contextProvider */
            $contextProvider = $c->get(ContextProviderInterface::class);
            $ctx = $contextProvider->getContext();

            if ($ctx->has($id)) {
                /** @var object */
                return $ctx->get($id);
            }

            if ($factory !== null) {
                $instance = $factory($c);
            } elseif ($implementation !== null) {
                /** @var DI\Container $c */
                $instance = $c->make($implementation);
            } else {
                /** @var DI\Container $c */
                $instance = $c->make($id);
            }

            if (!is_object($instance)) {
                throw new RuntimeException("Scoped factory for '{$id}' must return an object.");
            }

            $ctx->set($id, $instance);

            return $instance;
        });
    }

    private function compileTransient(string $id, ScopedDefinition $definition): mixed
    {
        $factory = $definition->factory;
        $implementation = $definition->implementation;

        return DI\factory(static function (ContainerInterface $c) use ($id, $factory, $implementation): object {
            if ($factory !== null) {
                $instance = $factory($c);
            } elseif ($implementation !== null) {
                /** @var DI\Container $c */
                $instance = $c->make($implementation);
            } else {
                /** @var DI\Container $c */
                $instance = $c->make($id);
            }

            if (!is_object($instance)) {
                throw new RuntimeException("Transient factory for '{$id}' must return an object.");
            }

            return $instance;
        });
    }

    private function applyDefaultScope(string $id, DI\Definition\Helper\DefinitionHelper $definition, Scope $defaultScope): mixed
    {
        // Raw PHP-DI definitions pass through — PHP-DI handles them natively
        // Default scope only applies to autowired classes without explicit definitions
        return $definition;
    }
}
