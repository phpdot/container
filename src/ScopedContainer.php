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
use PHPdot\Contracts\Container\ContextProviderInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ScopedContainer implements ContainerInterface, FactoryInterface
{
    /** @var array<string, true> */
    private array $scopedIds = [];

    /** @var array<string, true> */
    private array $transientIds = [];

    /** @var array<string, true> */
    private array $phpdiIds = [];

    /** @var array<string, Closure|null> */
    private array $factories = [];

    /** @var array<string, string|null> */
    private array $implementations = [];

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
    }

    /**
     * Register a scoped entry.
     *
     * @param class-string|null $implementation
     */
    public function registerScoped(string $id, Closure|null $factory = null, string|null $implementation = null): void
    {
        $this->scopedIds[$id] = true;
        $this->factories[$id] = $factory;
        $this->implementations[$id] = $implementation;
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
            isset($this->scopedIds[$id])    => 'SCOPED',
            isset($this->transientIds[$id]) => 'TRANSIENT',
            default                         => 'SINGLETON',
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
            $instance = $this->phpdi->make($target);
        }

        if (!is_object($instance)) {
            throw new RuntimeException("Resolution for '{$id}' must return an object.");
        }

        return $instance;
    }
}
