<?php

declare(strict_types=1);

/**
 * Scoped Container
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container;

use Closure;
use DI\Container;
use DI\FactoryInterface;
use PHPdot\Contracts\Container\ContextDestroyInterface;
use PHPdot\Contracts\Container\ContextProviderInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;
use Throwable;

final class ScopedContainer implements ContainerInterface, FactoryInterface
{
    /** @var array<string, true> */
    private array $scopedIds = [];

    /** @var array<string, true> */
    private array $transientIds = [];

    /** @var array<string, true> */
    private array $phpdiIds = [];

    /** @var array<string, true> */
    private array $phpdiKnownIds = [];

    /** @var array<string, Closure|null> */
    private array $factories = [];

    /** @var array<string, string|null> */
    private array $implementations = [];

    /** @var array<string, Closure(object, ContainerInterface): void> */
    private array $onDestroyCallbacks = [];

    private Container $phpdi;

    /**
     * @param ContextProviderInterface $contextProvider
     * @param array<string, array<string, string|Closure>> $contextualBindings
     */
    public function __construct(
        private readonly ContextProviderInterface $contextProvider,
        private readonly array $contextualBindings = [],
    ) {}

    /**
     * Set the underlying PHP-DI container. Called by ContainerBuilder after build.
     */
    public function setPhpDi(Container $phpdi): void
    {
        $this->phpdi = $phpdi;
        $this->phpdiKnownIds = array_fill_keys($phpdi->getKnownEntryNames(), true);
    }

    /**
     * Register a scoped entry.
     *
     * @param class-string|null $implementation
     * @param Closure(object, ContainerInterface): void|null $onDestroy
     *     Called at end of context (coroutine end in Swoole, reset() in FPM/CLI)
     *     when the active context implements `ContextDestroyInterface`.
     */
    public function registerScoped(
        string $id,
        Closure|null $factory = null,
        string|null $implementation = null,
        Closure|null $onDestroy = null,
    ): void {
        $this->scopedIds[$id] = true;
        $this->factories[$id] = $factory;
        $this->implementations[$id] = $implementation;
        if ($onDestroy !== null) {
            $this->onDestroyCallbacks[$id] = $onDestroy;
        }
    }

    /**
     * Register a transient entry.
     *
     * @param class-string|null $implementation
     */
    public function registerTransient(string $id, Closure|null $factory = null, string|null $implementation = null): void
    {
        $this->transientIds[$id] = true;
        $this->factories[$id] = $factory;
        $this->implementations[$id] = $implementation;
    }

    /**
     * Register an entry managed by PHP-DI (singletons, values, factories).
     */
    public function registerPhpDiId(string $id): void
    {
        $this->phpdiIds[$id] = true;
    }

    /**
     * Get a service. Checks scoped/transient first, then PHP-DI.
     */
    public function get(string $id): mixed
    {
        if (isset($this->scopedIds[$id])) {
            return $this->getScoped($id);
        }

        if (isset($this->transientIds[$id])) {
            return $this->resolve($id);
        }

        if (isset($this->phpdiIds[$id])) {
            return $this->phpdi->get($id);
        }

        if (isset($this->phpdiKnownIds[$id])) {
            return $this->phpdi->get($id);
        }

        if (class_exists($id)) {
            return $this->getScoped($id);
        }

        return $this->phpdi->get($id);
    }

    /**
     * Check if a service exists.
     */
    public function has(string $id): bool
    {
        return isset($this->scopedIds[$id])
            || isset($this->transientIds[$id])
            || isset($this->phpdiIds[$id])
            || $this->phpdi->has($id);
    }

    /**
     * Create a fresh instance. Respects scoped/transient entries.
     *
     * @param array<mixed> $parameters
     */
    public function make(string $name, array $parameters = []): mixed
    {
        if (isset($this->scopedIds[$name])) {
            return $this->getScoped($name);
        }

        if (isset($this->transientIds[$name])) {
            return $this->resolve($name);
        }

        return $this->phpdi->make($name, $parameters);
    }

    /**
     * Call a callable with autowired parameters.
     *
     * @param mixed $callable
     * @param array<mixed> $parameters
     */
    public function call(mixed $callable, array $parameters = []): mixed
    {
        /** @var callable $callable */
        return $this->phpdi->call($callable, $parameters);
    }

    /**
     * Get the underlying PHP-DI container.
     */
    public function phpdi(): Container
    {
        return $this->phpdi;
    }

    /**
     * List every registered service ID in this container — Scoped, Transient,
     * Singleton (via PHP-DI), plus anything PHP-DI knows about (PSR-17 bindings,
     * the container itself, etc.). Sorted alphabetically.
     *
     * Use this together with describe() to introspect the live container at
     * runtime: useful for debug pages, CLI tools, and tests.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        $ids = array_merge(
            array_keys($this->scopedIds),
            array_keys($this->transientIds),
            array_keys($this->phpdiIds),
            $this->phpdi->getKnownEntryNames(),
        );

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * Describe a registered entry — its scope and concrete implementation
     * (if explicitly aliased).
     *
     * The `implementation` field is the class the container will instantiate
     * when an alias is set (e.g. `Router::class → RouterRT::class` returns
     * `RouterRT::class`). Null means resolution goes through autowiring or
     * a factory closure — for the full PHP-DI debug string of singletons,
     * use `phpdi()->debugEntry($id)`.
     *
     * @return array{id: string, scope: string, implementation: string|null}
     */
    public function describe(string $id): array
    {
        $scope = match (true) {
            isset($this->scopedIds[$id])     => 'SCOPED',
            isset($this->transientIds[$id])  => 'TRANSIENT',
            isset($this->phpdiIds[$id])      => 'SINGLETON',
            isset($this->phpdiKnownIds[$id]) => 'SINGLETON',
            class_exists($id)                => 'SCOPED',
            default                          => 'SINGLETON',
        };

        return [
            'id'             => $id,
            'scope'          => $scope,
            'implementation' => $this->implementations[$id] ?? null,
        ];
    }

    /**
     * Get a scoped instance — cached within the current context.
     */
    private function getScoped(string $id): object
    {
        $ctx = $this->contextProvider->getContext();

        if ($ctx->has($id)) {
            /** @var object */
            return $ctx->get($id);
        }

        $instance = $this->resolve($id);
        $ctx->set($id, $instance);

        if (
            isset($this->onDestroyCallbacks[$id])
            && $ctx instanceof ContextDestroyInterface
        ) {
            $onDestroy = $this->onDestroyCallbacks[$id];
            $resolver = $this;
            $ctx->onDestroy(static function () use ($onDestroy, $instance, $resolver): void {
                try {
                    $onDestroy($instance, $resolver);
                } catch (Throwable) {
                    // Destroy callbacks must not propagate
                }
            });
        }

        return $instance;
    }

    /**
     * Resolve a fresh instance using factory, implementation, or autowiring.
     */
    private function resolve(string $id): object
    {
        $factory = $this->factories[$id] ?? null;

        if ($factory !== null) {
            $container = isset($this->contextualBindings[$id])
                ? new ContextualContainer($this, $this->contextualBindings[$id])
                : $this;
            $instance = $factory($container);
        } else {
            $target = $this->implementations[$id] ?? $id;
            $instance = $this->autowire($target, $id);
        }

        if (!is_object($instance)) {
            throw new RuntimeException("Resolution for '{$id}' must return an object.");
        }

        return $instance;
    }

    /**
     * Autowire a class without going through PHP-DI's resolution stack.
     *
     * PHP-DI's `make()` keeps a process-global `entriesBeingResolved` map to
     * detect circular dependencies. In a coroutine runtime that map leaks
     * across coroutines: if a dep's factory suspends mid-resolution (e.g.,
     * `Pool::borrow()` waiting on a `Channel`), a second coroutine entering
     * the same `make()` sees the stale flag and throws a false circular-dep.
     *
     * This method autowires by reflection alone, recursing back into
     * `$this->get()` for each typed dep — keeping resolution coroutine-safe
     * because the only state involved is the per-coroutine context cache.
     *
     * @param class-string|string $class Concrete class to instantiate
     * @param string $id Original entry id (for error messages)
     */
    private function autowire(string $class, string $id): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot autowire '{$id}': class '{$class}' does not exist.");
        }

        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Cannot autowire '{$id}': '{$class}' is not instantiable.");
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return $ref->newInstance();
        }

        $resolver = isset($this->contextualBindings[$id])
            ? new ContextualContainer($this, $this->contextualBindings[$id])
            : $this;

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            if ($param->isVariadic()) {
                break;
            }
            $args[] = $this->resolveParameter($param, $resolver, $class);
        }

        return $ref->newInstanceArgs($args);
    }

    /**
     * Resolve a single constructor parameter.
     */
    private function resolveParameter(ReflectionParameter $param, ContainerInterface $resolver, string $class): mixed
    {
        $type = $param->getType();

        // No type hint
        if ($type === null) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new RuntimeException(
                "Cannot autowire parameter \${$param->getName()} of {$class}: no type hint and no default value.",
            );
        }

        // Single named class — the common case
        if ($type instanceof ReflectionNamedType) {
            return $this->resolveNamedType($type, $param, $resolver, $class);
        }

        // Union: try each named class, fall back to default/null
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $sub) {
                if ($sub instanceof ReflectionNamedType && !$sub->isBuiltin()) {
                    try {
                        /** @var class-string $name */
                        $name = $sub->getName();
                        return $resolver->get($name);
                    } catch (Throwable) {
                        continue;
                    }
                }
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            if ($type->allowsNull()) {
                return null;
            }
            throw new RuntimeException(
                "Cannot autowire parameter \${$param->getName()} of {$class}: union type with no resolvable member.",
            );
        }

        // Intersection: PHP-DI doesn't support these for autowiring either
        if ($type instanceof ReflectionIntersectionType) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new RuntimeException(
                "Cannot autowire parameter \${$param->getName()} of {$class}: intersection types not supported.",
            );
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot autowire parameter \${$param->getName()} of {$class}: unsupported type.",
        );
    }

    /**
     * Resolve a single named type parameter.
     */
    private function resolveNamedType(
        ReflectionNamedType $type,
        ReflectionParameter $param,
        ContainerInterface $resolver,
        string $class,
    ): mixed {
        if ($type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            if ($type->allowsNull()) {
                return null;
            }
            throw new RuntimeException(
                "Cannot autowire parameter \${$param->getName()} of {$class}: builtin type '{$type->getName()}' has no default.",
            );
        }

        /** @var class-string $name */
        $name = $type->getName();

        try {
            return $resolver->get($name);
        } catch (Throwable $e) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            if ($type->allowsNull()) {
                return null;
            }
            throw $e;
        }
    }
}
