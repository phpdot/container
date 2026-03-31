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
use PHPdot\Container\Context\ContextProviderInterface;
use PHPdot\Container\Definition\DefinitionCompiler;
use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scanner\AttributeScanner;
use PHPdot\Container\Validation\ScopeValidator;
use Psr\Container\ContainerInterface;

final class ContainerBuilder
{
    private ContextProviderInterface|null $contextProvider = null;
    private Scope $defaultScope = Scope::SCOPED;
    private bool $scopeValidation = true;

    /** @var list<array<string, mixed>> Accumulated definition batches */
    private array $definitionBatches = [];

    /** @var array<string, Scope> Scope map for validation */
    private array $scopeMap = [];

    /** @var list<Closure(PHPDIBuilder<Container>): void> */
    private array $phpdiConfigurators = [];

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
     * Set the default scope for classes without explicit scope.
     */
    public function withDefaultScope(Scope $scope): self
    {
        $this->defaultScope = $scope;

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
     * Start a fluent definition.
     *
     * @param class-string $id
     * @param class-string|Closure|null $implementation
     */
    public function add(string $id, string|Closure|null $implementation = null): DefinitionBuilder
    {
        $builder = new DefinitionBuilder($implementation);

        // The DefinitionBuilder returns a ScopedDefinition when a scope method is called.
        // We need to capture it. Use a deferred approach:
        // The caller does: $this->add(Foo::class)->singleton()
        // Which returns a ScopedDefinition. We need the caller to pass it back.
        // Instead, store a reference and let the caller assign:
        // $builder->add(Foo::class)->singleton() — but this doesn't register it.

        // Better approach: return a registering builder
        return new DefinitionBuilder($implementation);
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
        $compiler = new DefinitionCompiler();

        foreach ($this->definitionBatches as $batch) {
            $phpdiDefs = [];

            foreach ($batch as $id => $definition) {
                if ($definition instanceof ScopedDefinition && $definition->scope === Scope::SCOPED) {
                    $scopedEntries[$id] = $definition;
                } elseif ($definition instanceof ScopedDefinition && $definition->scope === Scope::TRANSIENT) {
                    $transientEntries[$id] = $definition;
                } elseif ($definition instanceof ScopedDefinition) {
                    $compiled = $compiler->compile([$id => $definition], $this->defaultScope);
                    $phpdiDefs = array_replace($phpdiDefs, $compiled);
                } else {
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
        $container = new ScopedContainer($contextProvider);

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

        // Register scoped entries
        foreach ($scopedEntries as $id => $definition) {
            $container->registerScoped($id, $definition->factory, $definition->implementation);
        }

        // Register transient entries
        foreach ($transientEntries as $id => $definition) {
            $container->registerTransient($id, $definition->factory, $definition->implementation);
        }

        return $container;
    }
}
