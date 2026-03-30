<?php

declare(strict_types=1);

/**
 * Scope Validator
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Validation;

use DI\FactoryInterface;
use PHPdot\Container\Scope;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;

final class ScopeValidator
{
    /** @var list<string> Types that bypass scope validation */
    private const array SKIP_TYPES = [
        FactoryInterface::class,
        ContainerInterface::class,
        \DI\Container::class,
    ];

    /**
     * Validate the dependency graph.
     *
     * @param array<string, Scope> $scopes Map of service ID → scope
     * @throws ScopeMismatchException
     */
    public function validate(array $scopes): void
    {
        foreach ($scopes as $id => $scope) {
            if ($scope === Scope::TRANSIENT) {
                continue; // Transient can depend on anything
            }

            if (!class_exists($id)) {
                continue; // Non-class entries (scalars, interfaces with factories)
            }

            $this->validateDependencies($id, $scope, $scopes);
        }
    }

    /**
     * @param class-string $id
     * @param array<string, Scope> $scopes
     */
    private function validateDependencies(string $id, Scope $scope, array $scopes): void
    {
        $reflection = new ReflectionClass($id);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $depId = $type->getName();

            // Skip escape-hatch types
            foreach (self::SKIP_TYPES as $skipType) {
                if ($depId === $skipType || is_subclass_of($depId, $skipType)) {
                    continue 2;
                }
            }

            $depScope = $scopes[$depId] ?? null;

            if ($depScope === null) {
                continue; // Unknown scope — skip (might be auto-wired with default)
            }

            if (!$this->isAllowed($scope, $depScope)) {
                throw new ScopeMismatchException($id, $scope, $depId, $depScope);
            }
        }
    }

    private function isAllowed(Scope $parent, Scope $dependency): bool
    {
        return match ($parent) {
            Scope::SINGLETON => $dependency === Scope::SINGLETON,
            Scope::SCOPED => $dependency === Scope::SINGLETON || $dependency === Scope::SCOPED,
            Scope::TRANSIENT => true,
        };
    }
}
