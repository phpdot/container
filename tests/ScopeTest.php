<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;

use PHPdot\Container\ScopedContainer;

use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;

use function PHPdot\Container\transient;

use PHPUnit\Framework\TestCase;
use stdClass;

final class ScopeTest extends TestCase
{
    private TestContextProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestContextProvider();
    }

    // ─── Singleton ───

    public function testSingletonReturnsSameInstance(): void
    {
        $container = $this->build([
            'service' => singleton(function () {
                return new stdClass();
            }),
        ]);

        $a = $container->get('service');
        $b = $container->get('service');

        $this->assertSame($a, $b);
    }

    public function testSingletonSurvivesContextSwitch(): void
    {
        $container = $this->build([
            'service' => singleton(function () {
                return new stdClass();
            }),
        ]);

        $a = $container->get('service');
        $this->provider->newContext();
        $b = $container->get('service');

        $this->assertSame($a, $b);
    }

    // ─── Scoped ───

    public function testScopedReturnsSameInstanceWithinContext(): void
    {
        $container = $this->build([
            'service' => scoped(function () {
                return new stdClass();
            }),
        ]);

        $a = $container->get('service');
        $b = $container->get('service');

        $this->assertSame($a, $b);
    }

    public function testScopedReturnsDifferentInstanceAcrossContexts(): void
    {
        $container = $this->build([
            'service' => scoped(function () {
                return new stdClass();
            }),
        ]);

        $a = $container->get('service');
        $this->provider->newContext();
        $b = $container->get('service');

        $this->assertNotSame($a, $b);
    }

    public function testScopedWithClassAutowiring(): void
    {
        $container = $this->build([
            stdClass::class => scoped(),
        ]);

        $a = $container->get(stdClass::class);
        $b = $container->get(stdClass::class);
        $this->assertSame($a, $b);

        $this->provider->newContext();
        $c = $container->get(stdClass::class);
        $this->assertNotSame($a, $c);
    }

    // ─── Transient ───

    public function testTransientAlwaysReturnsNewInstance(): void
    {
        $container = $this->build([
            'service' => transient(function () {
                return new stdClass();
            }),
        ]);

        $a = $container->get('service');
        $b = $container->get('service');

        $this->assertNotSame($a, $b);
    }

    public function testTransientWithClassAutowiring(): void
    {
        $container = $this->build([
            stdClass::class => transient(),
        ]);

        $a = $container->get(stdClass::class);
        $b = $container->get(stdClass::class);

        $this->assertNotSame($a, $b);
    }

    // ─── Mixed scopes ───

    public function testMixedScopes(): void
    {
        $container = $this->build([
            'single' => singleton(function () {
                $o = new stdClass();
                $o->type = 'singleton';

                return $o;
            }),
            'scoped_svc' => scoped(function () {
                $o = new stdClass();
                $o->type = 'scoped';

                return $o;
            }),
            'transient_svc' => transient(function () {
                $o = new stdClass();
                $o->type = 'transient';

                return $o;
            }),
        ]);

        // Request 1
        $s1 = $container->get('single');
        $sc1 = $container->get('scoped_svc');
        $t1 = $container->get('transient_svc');
        $t2 = $container->get('transient_svc');

        $this->assertNotSame($t1, $t2, 'Transient should be different');

        // Request 2
        $this->provider->newContext();
        $s2 = $container->get('single');
        $sc2 = $container->get('scoped_svc');

        $this->assertSame($s1, $s2, 'Singleton should survive context switch');
        $this->assertNotSame($sc1, $sc2, 'Scoped should be different across contexts');
    }

    // ─── PHP-DI values ───

    public function testPhpDiValueDefinition(): void
    {
        $container = $this->build([
            'config.name' => \DI\value('PHPdot'),
            'config.port' => \DI\value(8080),
        ]);

        $this->assertSame('PHPdot', $container->get('config.name'));
        $this->assertSame(8080, $container->get('config.port'));
    }

    // ─── has() ───

    public function testHasReturnsTrueForAllScopes(): void
    {
        $container = $this->build([
            'single' => singleton(fn() => new stdClass()),
            'scoped_svc' => scoped(fn() => new stdClass()),
            'transient_svc' => transient(fn() => new stdClass()),
            'value' => \DI\value('hello'),
        ]);

        $this->assertTrue($container->has('single'));
        $this->assertTrue($container->has('scoped_svc'));
        $this->assertTrue($container->has('transient_svc'));
        $this->assertTrue($container->has('value'));
        $this->assertFalse($container->has('nonexistent'));
    }

    // ─── make() ───

    public function testMakeAlwaysCreatesNewInstance(): void
    {
        $container = $this->build([
            stdClass::class => singleton(),
        ]);

        $a = $container->get(stdClass::class);
        $b = $container->make(stdClass::class);

        $this->assertNotSame($a, $b, 'make() should bypass cache');
    }

    // ─── Context resetter ───

    public function testContextResetterClearsContext(): void
    {
        $container = $this->build([
            'service' => scoped(function () {
                return new stdClass();
            }),
        ]);

        $a = $container->get('service');

        /** @var \PHPdot\Container\ContextResetter $resetter */
        $resetter = $container->get(\PHPdot\Container\ContextResetter::class);
        $resetter->reset();

        $b = $container->get('service');
        $this->assertNotSame($a, $b, 'After reset, scoped should return new instance');
    }

    // ─── Factory receives container ───

    public function testScopedFactoryReceivesContainer(): void
    {
        $container = $this->build([
            'dep' => \DI\value('injected-value'),
            'service' => scoped(function ($c) {
                $obj = new stdClass();
                $obj->dep = $c->get('dep');

                return $obj;
            }),
        ]);

        /** @var stdClass $service */
        $service = $container->get('service');
        $this->assertSame('injected-value', $service->dep);
    }

    // ─── Multiple contexts ───

    public function testMultipleNamedContexts(): void
    {
        $container = $this->build([
            'service' => scoped(function () {
                $o = new stdClass();
                $o->id = uniqid('', true);

                return $o;
            }),
        ]);

        $this->provider->newContext('user-1');
        $a = $container->get('service');

        $this->provider->newContext('user-2');
        $b = $container->get('service');

        $this->provider->newContext('user-1');
        // Note: TestContextProvider creates new ArrayContext for each newContext call
        // so going back to 'user-1' is a NEW context with that name
        $c = $container->get('service');

        $this->assertNotSame($a, $b);
    }

    // ─── Default scoped for unregistered classes ───

    public function testUnregisteredClassDefaultsToScoped(): void
    {
        $container = $this->build([]);

        $a = $container->get(stdClass::class);
        $b = $container->get(stdClass::class);
        $this->assertSame($a, $b, 'Same instance within context');

        $this->provider->newContext();
        $c = $container->get(stdClass::class);
        $this->assertNotSame($a, $c, 'Different instance across contexts');
    }

    public function testExplicitSingletonNotAffectedByDefaultScoped(): void
    {
        $container = $this->build([
            stdClass::class => singleton(),
        ]);

        $a = $container->get(stdClass::class);
        $this->provider->newContext();
        $b = $container->get(stdClass::class);

        $this->assertSame($a, $b, 'Explicit singleton survives context switch');
    }

    public function testDiValueNotAffectedByDefaultScoped(): void
    {
        $container = $this->build([
            'config.name' => \DI\value('PHPdot'),
        ]);

        $this->assertSame('PHPdot', $container->get('config.name'));

        $this->provider->newContext();
        $this->assertSame('PHPdot', $container->get('config.name'));
    }

    public function testDiFactoryNotAffectedByDefaultScoped(): void
    {
        $counter = 0;
        $container = $this->build([
            'service' => \DI\factory(function () use (&$counter) {
                $counter++;

                return new stdClass();
            }),
        ]);

        $a = $container->get('service');
        $this->provider->newContext();
        $b = $container->get('service');

        $this->assertSame($a, $b, 'DI\\factory() remains singleton');
        $this->assertSame(1, $counter);
    }

    public function testContextResetterStaysSingleton(): void
    {
        $container = $this->build([]);

        $a = $container->get(\PHPdot\Container\ContextResetter::class);
        $this->provider->newContext();
        $b = $container->get(\PHPdot\Container\ContextResetter::class);

        $this->assertSame($a, $b);
    }

    public function testUnregisteredClassWithDependencyDefaultsToScoped(): void
    {
        $container = $this->build([]);

        /** @var UnregisteredWithDep $a */
        $a = $container->get(UnregisteredWithDep::class);
        $this->assertInstanceOf(UnregisteredDep::class, $a->dep);

        /** @var UnregisteredWithDep $b */
        $b = $container->get(UnregisteredWithDep::class);
        $this->assertSame($a, $b, 'Same within context');

        $this->provider->newContext();

        /** @var UnregisteredWithDep $c */
        $c = $container->get(UnregisteredWithDep::class);
        $this->assertNotSame($a, $c, 'Different across contexts');
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

class UnregisteredDep {}

class UnregisteredWithDep
{
    public function __construct(
        public readonly UnregisteredDep $dep,
    ) {}
}
