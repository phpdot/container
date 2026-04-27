<?php

declare(strict_types=1);

/**
 * Attribute Scanner
 *
 * Thin adapter over `phpdot/attribute`'s generic Scanner. Maps the three
 * lifecycle attributes (`#[Singleton]`, `#[Scoped]`, `#[Transient]`) to the
 * corresponding `Scope` enum value.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Scanner;

use PHPdot\Attribute\Scanner;
use PHPdot\Container\Attribute\Scoped;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Container\Attribute\Transient;
use PHPdot\Container\Scope;
use ReflectionClass;

final class AttributeScanner
{
    /**
     * Map of lifecycle attribute class -> Scope enum.
     *
     * @var array<class-string, Scope>
     */
    private const array SCOPE_MAP = [
        Singleton::class => Scope::SINGLETON,
        Scoped::class    => Scope::SCOPED,
        Transient::class => Scope::TRANSIENT,
    ];

    public function __construct(
        private readonly Scanner $scanner = new Scanner(),
    ) {}

    /**
     * Scan a directory for classes carrying lifecycle attributes.
     *
     * @return array<class-string, Scope> Map of class name -> scope
     */
    public function scanDirectory(string $directory): array
    {
        $registry = $this->scanner->scan(
            directories: [$directory],
            filter: array_keys(self::SCOPE_MAP),
            forceRescan: true,
        );

        $results = [];

        foreach (self::SCOPE_MAP as $attributeClass => $scope) {
            foreach ($registry->findByAttribute($attributeClass) as $result) {
                $results[$result->class] = $scope;
            }
        }

        return $results;
    }

    /**
     * Get the scope from a class's attributes via reflection.
     *
     * @param class-string $className
     */
    public function getScopeFromAttributes(string $className): Scope|null
    {
        $reflection = new ReflectionClass($className);

        foreach (self::SCOPE_MAP as $attributeClass => $scope) {
            if ($reflection->getAttributes($attributeClass) !== []) {
                return $scope;
            }
        }

        return null;
    }
}
