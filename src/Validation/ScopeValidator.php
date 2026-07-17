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
    /**
     * @var list<string> Types that bypass scope validation
     */
    private const array SKIP_TYPES = [
        FactoryInterface::class,
        ContainerInterface::class,
        \DI\Container::class,
    ];

    /**
     * Validate the dependency graph.
     *
     * @param array<string, Scope> $scopes Map of service ID → scope
     *
     * @throws ScopeMismatchException
     *
     * @return void
     */
    public function validate(array $scopes): void
    {
        foreach ($scopes as $id => $scope) {
            if ($scope === Scope::TRANSIENT) {
                continue;
            }

            if (!class_exists($id)) {
                continue;
            }

            $this->validateDependencies($id, $scope, $scopes);
        }
    }

    /**
     * Check each constructor dependency scope against the consumer scope.
     *
     * Types listed in SKIP_TYPES are escape hatches and are exempt.
     *
     * @param class-string $id
     * @param array<string, Scope> $scopes
     * @param Scope $scope
     *
     * @return void
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

            foreach (self::SKIP_TYPES as $skipType) {
                if ($depId === $skipType || is_subclass_of($depId, $skipType)) {
                    continue 2;
                }
            }

            $depScope = $scopes[$depId] ?? null;

            if ($depScope === null) {
                continue;
            }

            if (!$this->isAllowed($scope, $depScope)) {
                throw new ScopeMismatchException($id, $scope, $depId, $depScope);
            }
        }
    }

    /**
     * Is allowed.
     *
     * @param Scope $parent
     * @param Scope $dependency
     *
     * @return bool
     */
    private function isAllowed(Scope $parent, Scope $dependency): bool
    {
        return match ($parent) {
            Scope::SINGLETON => $dependency === Scope::SINGLETON,
            Scope::SCOPED => $dependency === Scope::SINGLETON || $dependency === Scope::SCOPED,
            Scope::TRANSIENT => true,
        };
    }
}
