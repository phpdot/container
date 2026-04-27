<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use DI\FactoryInterface;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Scope;

use function PHPdot\Container\scoped;

use PHPdot\Container\ScopedContainer;

use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;

use function PHPdot\Container\transient;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class InterfaceBindingTest extends TestCase
{
    private TestContextProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestContextProvider();
    }

    // ─── Bug #1: Implementation binding for scoped ───

    public function testScopedInterfaceToImplementation(): void
    {
        $container = $this->build([
            BugTestInterface::class => scoped(BugTestImplementation::class),
        ]);

        $instance = $container->get(BugTestInterface::class);

        $this->assertInstanceOf(BugTestImplementation::class, $instance);
    }

    public function testTransientInterfaceToImplementation(): void
    {
        $container = $this->build([
            BugTestInterface::class => transient(BugTestImplementation::class),
        ]);

        $a = $container->get(BugTestInterface::class);
        $b = $container->get(BugTestInterface::class);

        $this->assertInstanceOf(BugTestImplementation::class, $a);
        $this->assertNotSame($a, $b);
    }

    public function testScopedImplementationIsolatedAcrossContexts(): void
    {
        $container = $this->build([
            BugTestInterface::class => scoped(BugTestImplementation::class),
        ]);

        $a = $container->get(BugTestInterface::class);

        $this->provider->newContext();

        $b = $container->get(BugTestInterface::class);

        $this->assertNotSame($a, $b);
    }

    // ─── Bug #2: FactoryInterface escape hatch ───

    public function testFactoryInterfaceResolvesScoped(): void
    {
        $container = $this->build([
            BugTestInterface::class => scoped(BugTestImplementation::class),
        ]);

        /** @var FactoryInterface $factory */
        $factory = $container->get(FactoryInterface::class);

        $instance = $factory->make(BugTestInterface::class);

        $this->assertInstanceOf(BugTestImplementation::class, $instance);
    }

    public function testFactoryInterfaceReturnsScopedContainer(): void
    {
        $container = $this->build([]);

        /** @var FactoryInterface $factory */
        $factory = $container->get(FactoryInterface::class);

        $this->assertInstanceOf(ScopedContainer::class, $factory);
    }

    // ─── Bug #3: ContainerInterface resolves through ScopedContainer ───

    public function testContainerInterfaceReturnsScopedContainer(): void
    {
        $container = $this->build([]);

        /** @var ContainerInterface $resolved */
        $resolved = $container->get(ContainerInterface::class);

        $this->assertInstanceOf(ScopedContainer::class, $resolved);
    }

    public function testContainerInterfaceCanResolveScoped(): void
    {
        $container = $this->build([
            BugTestInterface::class => scoped(BugTestImplementation::class),
        ]);

        /** @var ContainerInterface $resolved */
        $resolved = $container->get(ContainerInterface::class);

        $instance = $resolved->get(BugTestInterface::class);

        $this->assertInstanceOf(BugTestImplementation::class, $instance);
    }

    public function testInjectedContainerSeesScoped(): void
    {
        $container = $this->build([
            BugTestInterface::class => scoped(BugTestImplementation::class),
            ServiceWithContainer::class => singleton(),
        ]);

        /** @var ServiceWithContainer $service */
        $service = $container->get(ServiceWithContainer::class);

        $this->assertInstanceOf(BugTestImplementation::class, $service->resolve());
    }

    // ─── Helper ───

    /**
     * @param array<string, mixed> $definitions
     */
    private function build(array $definitions): ScopedContainer
    {
        return (new ContainerBuilder())
            ->withContextProvider($this->provider)
            ->withScopeValidation(false)
            ->addDefinitions($definitions)
            ->build();
    }
}

// ─── Test fixtures ───

interface BugTestInterface
{
    public function value(): string;
}

class BugTestImplementation implements BugTestInterface
{
    public function value(): string
    {
        return 'implementation';
    }
}

class ServiceWithContainer
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function resolve(): object
    {
        return $this->container->get(BugTestInterface::class);
    }
}
