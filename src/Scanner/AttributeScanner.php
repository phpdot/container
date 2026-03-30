<?php

declare(strict_types=1);

/**
 * Attribute Scanner
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Scanner;

use PHPdot\Container\Attribute\Scoped;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Container\Attribute\Transient;
use PHPdot\Container\Scope;
use ReflectionClass;

final class AttributeScanner
{
    /**
     * Scan a directory for PHP classes with scope attributes.
     *
     * @return array<string, Scope> Map of class name → scope
     */
    public function scanDirectory(string $directory): array
    {
        $results = [];
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return $results;
        }

        foreach ($files as $file) {
            $className = $this->extractClassName($file);

            if ($className === null || !class_exists($className)) {
                continue;
            }

            $scope = $this->getScopeFromAttributes($className);

            if ($scope !== null) {
                $results[$className] = $scope;
            }
        }

        // Recurse into subdirectories
        $dirs = glob($directory . '/*', GLOB_ONLYDIR);

        if ($dirs !== false) {
            foreach ($dirs as $subDir) {
                $results = array_replace($results, $this->scanDirectory($subDir));
            }
        }

        return $results;
    }

    /**
     * Get the scope from a class's attributes.
     *
     * @param class-string $className
     */
    public function getScopeFromAttributes(string $className): Scope|null
    {
        $reflection = new ReflectionClass($className);

        if ($reflection->getAttributes(Singleton::class) !== []) {
            return Scope::SINGLETON;
        }

        if ($reflection->getAttributes(Scoped::class) !== []) {
            return Scope::SCOPED;
        }

        if ($reflection->getAttributes(Transient::class) !== []) {
            return Scope::TRANSIENT;
        }

        return null;
    }

    private function extractClassName(string $file): string|null
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches) === 1) {
            $namespace = $matches[1];
        }

        if (preg_match('/(?:class|enum|interface)\s+(\w+)/', $content, $matches) === 1) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }
}
