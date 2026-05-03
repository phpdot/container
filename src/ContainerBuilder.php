<?php

declare(strict_types=1);

/**
 * Container Builder
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container;

use Closure;
use DI\Container;
use DI\ContainerBuilder as PHPDIBuilder;
use DI\FactoryInterface;
use PHPdot\Container\Context\ArrayContextProvider;
use PHPdot\Container\Definition\DefinitionCompiler;
use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scanner\AttributeScanner;
use PHPdot\Container\Validation\ScopeValidator;
use PHPdot\Contracts\Container\ContextProviderInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ContainerBuilder
{
    private ContextProviderInterface|null $contextProvider = null;
    private bool $scopeValidation = true;

    /** @var list<array<string, mixed>> Accumulated definition batches */
    private array $definitionBatches = [];

    /** @var array<string, Scope> Scope map for validation */
    private array $scopeMap = [];

    /** @var list<Closure(PHPDIBuilder<Container>): void> */
    private array $phpdiConfigurators = [];

    /** @var array<string, array<string, string|Closure>> */
    private array $contextualBindings = [];

    private string|null $compilationDir = null;
    private string|null $proxyDir = null;

    /**
     * Set the context provider. Required for Scoped to work beyond FPM.
     */
    public function withContextProvider(ContextProviderInterface $provider): self
    {
        $this->contextProvider = $provider;

        return $this;
    }

    /**
     * Enable or disable build-time scope validation.
     */
    public function withScopeValidation(bool $enabled): self
    {
        $this->scopeValidation = $enabled;

        return $this;
    }

    /**
     * Start a fluent definition that auto-registers when a scope method is called.
     *
     * @param class-string $id The service identifier (class or interface name)
     * @param class-string|Closure|null $implementation Concrete class or factory
     */
    public function add(string $id, string|Closure|null $implementation = null): RegisteringDefinitionBuilder
    {
        $factory = null;
        $impl = null;

        if ($implementation instanceof Closure) {
            $factory = $implementation;
        } else {
            $impl = $implementation;
        }

        return new RegisteringDefinitionBuilder($this, $id, $impl, $factory);
    }

    /**
     * @param class-string $consumer
     */
    public function when(string $consumer): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $consumer);
    }

    public function addContextualBinding(string $consumer, string $abstract, string|Closure $concrete): void
    {
        $this->contextualBindings[$consumer][$abstract] = $concrete;
    }

    /**
     * Register a definition from a fluent builder result.
     */
    public function register(string $id, ScopedDefinition $definition): self
    {
        $this->addDefinitions([$id => $definition]);

        return $this;
    }

    /**
     * Add definitions from an array (definition files).
     *
     * @param array<string, mixed> $definitions
     */
    public function addDefinitions(array $definitions): self
    {
        foreach ($definitions as $id => $definition) {
            if ($definition instanceof ScopedDefinition) {
                $this->scopeMap[$id] = $definition->scope;
            }
        }

        $this->definitionBatches[] = $definitions;

        return $this;
    }

    /**
     * Load definitions from a PHP file that returns an array.
     *
     * The file must `return [...]` an array of definitions in the same shape
     * as accepted by addDefinitions(). Throws if the file is missing or does
     * not return an array — silent no-ops hide bugs.
     */
    public function addDefinitionsFromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Definitions file not found: {$path}");
        }

        /** @var mixed $definitions */
        $definitions = require $path;

        if (!is_array($definitions)) {
            throw new RuntimeException("Definitions file must return an array: {$path}");
        }

        /** @var array<string, mixed> $definitions */
        return $this->addDefinitions($definitions);
    }

    /**
     * Scan classes in a directory for scope attributes.
     */
    public function scanAttributesIn(string $directory): self
    {
        $scanner = new AttributeScanner();
        $scoped = $scanner->scanDirectory($directory);

        $defs = [];
        foreach ($scoped as $className => $scope) {
            $defs[$className] = new ScopedDefinition($scope);
        }

        if ($defs !== []) {
            $this->addDefinitions($defs);
        }

        return $this;
    }

    /**
     * Enable PHP-DI compilation for production.
     */
    public function enableCompilation(string $directory): self
    {
        $this->compilationDir = $directory;

        return $this;
    }

    /**
     * Enable PHP-DI proxy generation.
     */
    public function enableProxies(string $directory): self
    {
        $this->proxyDir = $directory;

        return $this;
    }

    /**
     * Raw PHP-DI builder access for advanced configuration.
     *
     * @param Closure(PHPDIBuilder<Container>): void $configurator
     */
    public function configurePHPDI(Closure $configurator): self
    {
        $this->phpdiConfigurators[] = $configurator;

        return $this;
    }

    /**
     * Build and return a scoped container.
     */
    public function build(): ScopedContainer
    {
        $contextProvider = $this->contextProvider ?? new ArrayContextProvider();

        // Separate scoped definitions from everything else
        /** @var list<array<string, mixed>> PHP-DI definition batches */
        $phpdiDefBatches = [];
        $scopedEntries = [];
        $transientEntries = [];
        /** @var list<string> */
        $phpdiIds = [];
        $compiler = new DefinitionCompiler();

        foreach ($this->definitionBatches as $batch) {
            $phpdiDefs = [];

            foreach ($batch as $id => $definition) {
                if ($definition instanceof ScopedDefinition && $definition->scope === Scope::SCOPED) {
                    $scopedEntries[$id] = $definition;
                } elseif ($definition instanceof ScopedDefinition && $definition->scope === Scope::TRANSIENT) {
                    $transientEntries[$id] = $definition;
                } elseif ($definition instanceof ScopedDefinition) {
                    $phpdiIds[] = $id;
                    if (isset($this->contextualBindings[$id]) && $definition->factory !== null) {
                        $bindings = $this->contextualBindings[$id];
                        $original = $definition->factory;
                        $wrapped = static function (ContainerInterface $c) use ($original, $bindings): mixed {
                            assert($c instanceof ScopedContainer);

                            return $original(new ContextualContainer($c, $bindings));
                        };
                        $definition = new ScopedDefinition(
                            $definition->scope,
                            $definition->implementation,
                            $wrapped,
                            $definition->onDestroy,
                        );
                    }
                    $phpdiDefs[$id] = $compiler->compileSingleton($id, $definition);
                } else {
                    $phpdiIds[] = $id;
                    $phpdiDefs[$id] = $definition;
                }
            }

            if ($phpdiDefs !== []) {
                $phpdiDefBatches[] = $phpdiDefs;
            }
        }

        // Validate scope dependencies
        if ($this->scopeValidation && $this->scopeMap !== []) {
            $validator = new ScopeValidator();
            $validator->validate($this->scopeMap);
        }

        // Create scoped container first (for delegate lookup)
        $container = new ScopedContainer($contextProvider, $this->contextualBindings);

        // Build PHP-DI with wrapContainer so deps resolve through ScopedContainer
        $phpdiBuilder = new PHPDIBuilder();
        $phpdiBuilder->wrapContainer($container);

        foreach ($phpdiDefBatches as $batch) {
            $phpdiBuilder->addDefinitions($batch);
        }

        $phpdiBuilder->addDefinitions([
            ContextProviderInterface::class => $contextProvider,
            ContextResetter::class => \DI\factory(static function () use ($contextProvider): ContextResetter {
                return new ContextResetter($contextProvider);
            }),
        ]);

        if ($this->compilationDir !== null) {
            $phpdiBuilder->enableCompilation($this->compilationDir);
        }

        if ($this->proxyDir !== null) {
            $phpdiBuilder->writeProxiesToFile(true, $this->proxyDir);
        }

        foreach ($this->phpdiConfigurators as $configurator) {
            $configurator($phpdiBuilder);
        }

        $phpdi = $phpdiBuilder->build();
        $container->setPhpDi($phpdi);

        $phpdi->set(ContainerInterface::class, $container);
        $phpdi->set(FactoryInterface::class, $container);

        foreach ($phpdiIds as $id) {
            $container->registerPhpDiId($id);
        }

        $container->registerPhpDiId(ContextProviderInterface::class);
        $container->registerPhpDiId(ContextResetter::class);
        $container->registerPhpDiId(ContainerInterface::class);
        $container->registerPhpDiId(FactoryInterface::class);

        foreach ($scopedEntries as $id => $definition) {
            $container->registerScoped(
                $id,
                $definition->factory,
                $definition->implementation,
                $definition->onDestroy,
            );
        }

        foreach ($transientEntries as $id => $definition) {
            $container->registerTransient($id, $definition->factory, $definition->implementation);
        }

        return $container;
    }
}
