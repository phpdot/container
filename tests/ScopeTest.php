<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;

use PHPdot\Container\ScopedContainer;

use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;

use function PHPdot\Container\transient;

use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function singletonReturnsSameInstance(): void
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

    #[Test]
    public function singletonSurvivesContextSwitch(): void
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

    #[Test]
    public function scopedReturnsSameInstanceWithinContext(): void
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

    #[Test]
    public function scopedReturnsDifferentInstanceAcrossContexts(): void
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

    #[Test]
    public function scopedWithClassAutowiring(): void
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

    #[Test]
    public function transientAlwaysReturnsNewInstance(): void
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

    #[Test]
    public function transientWithClassAutowiring(): void
    {
        $container = $this->build([
            stdClass::class => transient(),
        ]);

        $a = $container->get(stdClass::class);
        $b = $container->get(stdClass::class);

        $this->assertNotSame($a, $b);
    }

    // ─── Mixed scopes ───

    #[Test]
    public function mixedScopes(): void
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

    #[Test]
    public function phpDiValueDefinition(): void
    {
        $container = $this->build([
            'config.name' => \DI\value('PHPdot'),
            'config.port' => \DI\value(8080),
        ]);

        $this->assertSame('PHPdot', $container->get('config.name'));
        $this->assertSame(8080, $container->get('config.port'));
    }

    // ─── has() ───

    #[Test]
    public function hasReturnsTrueForAllScopes(): void
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

    #[Test]
    public function makeAlwaysCreatesNewInstance(): void
    {
        $container = $this->build([
            stdClass::class => singleton(),
        ]);

        $a = $container->get(stdClass::class);
        $b = $container->make(stdClass::class);

        $this->assertNotSame($a, $b, 'make() should bypass cache');
    }

    // ─── Context resetter ───

    #[Test]
    public function contextResetterClearsContext(): void
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

    #[Test]
    public function scopedFactoryReceivesContainer(): void
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

    #[Test]
    public function multipleNamedContexts(): void
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

    #[Test]
    public function unregisteredClassDefaultsToScoped(): void
    {
        $container = $this->build([]);

        $a = $container->get(stdClass::class);
        $b = $container->get(stdClass::class);
        $this->assertSame($a, $b, 'Same instance within context');

        $this->provider->newContext();
        $c = $container->get(stdClass::class);
        $this->assertNotSame($a, $c, 'Different instance across contexts');
    }

    #[Test]
    public function explicitSingletonNotAffectedByDefaultScoped(): void
    {
        $container = $this->build([
            stdClass::class => singleton(),
        ]);

        $a = $container->get(stdClass::class);
        $this->provider->newContext();
        $b = $container->get(stdClass::class);

        $this->assertSame($a, $b, 'Explicit singleton survives context switch');
    }

    #[Test]
    public function diValueNotAffectedByDefaultScoped(): void
    {
        $container = $this->build([
            'config.name' => \DI\value('PHPdot'),
        ]);

        $this->assertSame('PHPdot', $container->get('config.name'));

        $this->provider->newContext();
        $this->assertSame('PHPdot', $container->get('config.name'));
    }

    #[Test]
    public function diFactoryNotAffectedByDefaultScoped(): void
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

    #[Test]
    public function contextResetterStaysSingleton(): void
    {
        $container = $this->build([]);

        $a = $container->get(\PHPdot\Container\ContextResetter::class);
        $this->provider->newContext();
        $b = $container->get(\PHPdot\Container\ContextResetter::class);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function unregisteredClassWithDependencyDefaultsToScoped(): void
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
