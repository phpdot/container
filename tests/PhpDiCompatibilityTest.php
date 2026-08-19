<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\scoped;
use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;

use function PHPdot\Container\transient;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

/**
 * Verifies that all PHP-DI features work through phpdot/container.
 */
final class PhpDiCompatibilityTest extends TestCase
{
    private TestContextProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestContextProvider();
    }

    // ─── DI\value() ───

    #[Test]
    public function diValueString(): void
    {
        $c = $this->build(['name' => \DI\value('PHPdot')]);
        $this->assertSame('PHPdot', $c->get('name'));
    }

    #[Test]
    public function diValueInt(): void
    {
        $c = $this->build(['port' => \DI\value(8080)]);
        $this->assertSame(8080, $c->get('port'));
    }

    #[Test]
    public function diValueArray(): void
    {
        $c = $this->build(['list' => \DI\value([1, 2, 3])]);
        $this->assertSame([1, 2, 3], $c->get('list'));
    }

    #[Test]
    public function diValueNull(): void
    {
        $c = $this->build(['nothing' => \DI\value(null)]);
        $this->assertNull($c->get('nothing'));
    }

    // ─── DI\factory() ───

    #[Test]
    public function diFactory(): void
    {
        $c = $this->build([
            'service' => \DI\factory(function () {
                return new stdClass();
            }),
        ]);

        $a = $c->get('service');
        $b = $c->get('service');
        $this->assertInstanceOf(stdClass::class, $a);
        $this->assertSame($a, $b, 'PHP-DI factory is singleton by default');
    }

    #[Test]
    public function diFactoryReceivesContainer(): void
    {
        $c = $this->build([
            'dep' => \DI\value('hello'),
            'service' => \DI\factory(function (ContainerInterface $c) {
                $obj = new stdClass();
                $obj->dep = $c->get('dep');

                return $obj;
            }),
        ]);

        /** @var stdClass $service */
        $service = $c->get('service');
        $this->assertSame('hello', $service->dep);
    }

    // ─── DI\autowire() ───

    #[Test]
    public function diAutowire(): void
    {
        $c = $this->build([
            AutowiredService::class => \DI\autowire(),
        ]);

        $service = $c->get(AutowiredService::class);
        $this->assertInstanceOf(AutowiredService::class, $service);
    }

    #[Test]
    public function diAutowireWithConstructorParameter(): void
    {
        $c = $this->build([
            NamedService::class => \DI\autowire()
                ->constructorParameter('name', 'PHPdot'),
        ]);

        /** @var NamedService $service */
        $service = $c->get(NamedService::class);
        $this->assertSame('PHPdot', $service->name);
    }

    // ─── DI\create() ───

    #[Test]
    public function diCreate(): void
    {
        $c = $this->build([
            stdClass::class => \DI\create(),
        ]);

        $this->assertInstanceOf(stdClass::class, $c->get(stdClass::class));
    }

    // ─── DI\get() (reference) ───

    #[Test]
    public function diGet(): void
    {
        $c = $this->build([
            'original' => \DI\value('the-value'),
            'alias' => \DI\get('original'),
        ]);

        $this->assertSame('the-value', $c->get('alias'));
    }

    // ─── DI\env() ───

    #[Test]
    public function diEnv(): void
    {
        $_ENV['TEST_CONTAINER_VAR'] = 'from-env';

        $c = $this->build([
            'env_val' => \DI\env('TEST_CONTAINER_VAR', 'default'),
        ]);

        $this->assertSame('from-env', $c->get('env_val'));

        unset($_ENV['TEST_CONTAINER_VAR']);
    }

    #[Test]
    public function diEnvDefault(): void
    {
        $c = $this->build([
            'env_val' => \DI\env('NONEXISTENT_VAR_12345', 'fallback'),
        ]);

        $this->assertSame('fallback', $c->get('env_val'));
    }

    // ─── DI\string() ───

    #[Test]
    public function diString(): void
    {
        $c = $this->build([
            'app.name' => \DI\value('PHPdot'),
            'greeting' => \DI\string('Hello {app.name}!'),
        ]);

        $this->assertSame('Hello PHPdot!', $c->get('greeting'));
    }

    // ─── DI\decorate() ───

    #[Test]
    public function diDecorate(): void
    {
        $builder = (new ContainerBuilder())
            ->withContextProvider($this->provider)
            ->withScopeValidation(false);

        // Two separate addDefinitions calls — PHP-DI merges them
        $builder->addDefinitions([
            'service' => \DI\factory(function () {
                $obj = new stdClass();
                $obj->value = 'original';

                return $obj;
            }),
        ]);
        $builder->addDefinitions([
            'service' => \DI\decorate(function (stdClass $previous) {
                $previous->value = 'decorated';

                return $previous;
            }),
        ]);

        $c = $builder->build();

        /** @var stdClass $service */
        $service = $c->get('service');
        $this->assertSame('decorated', $service->value);
    }

    // ─── Autowiring (implicit) ───

    #[Test]
    public function implicitAutowiring(): void
    {
        $c = $this->build([]);

        // PHP-DI autowires any existing class even without explicit definition
        $obj = $c->get(stdClass::class);
        $this->assertInstanceOf(stdClass::class, $obj);
    }

    #[Test]
    public function autowiringWithDependencies(): void
    {
        $c = $this->build([
            SimpleDep::class => \DI\create(),
        ]);

        /** @var ServiceWithDep $service */
        $service = $c->get(ServiceWithDep::class);
        $this->assertInstanceOf(SimpleDep::class, $service->dep);
    }

    // ─── Interface → Implementation binding ───

    #[Test]
    public function interfaceBinding(): void
    {
        $c = $this->build([
            SimpleInterface::class => \DI\autowire(SimpleImplementation::class),
        ]);

        $service = $c->get(SimpleInterface::class);
        $this->assertInstanceOf(SimpleImplementation::class, $service);
    }

    // ─── $container->call() ───

    #[Test]
    public function containerCall(): void
    {
        $c = $this->build([
            'greeting' => \DI\value('Hello'),
        ]);

        /** @var string $result */
        $result = $c->call(function (string $greeting) {
            return $greeting . ' World';
        }, ['greeting' => 'Hello']);

        $this->assertSame('Hello World', $result);
    }

    // ─── $container->make() ───

    #[Test]
    public function containerMakeAlwaysFresh(): void
    {
        $c = $this->build([
            stdClass::class => \DI\create(),
        ]);

        $a = $c->get(stdClass::class);
        $b = $c->make(stdClass::class);

        $this->assertNotSame($a, $b);
    }

    // ─── PSR-11 compliance ───

    #[Test]
    public function psr11GetThrowsOnMissing(): void
    {
        $c = $this->build([]);

        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);
        $c->get('nonexistent.service.12345');
    }

    #[Test]
    public function psr11HasReturnsFalseForMissing(): void
    {
        $c = $this->build([]);

        $this->assertFalse($c->has('nonexistent.service.12345'));
    }

    #[Test]
    public function psr11HasReturnsTrueForExisting(): void
    {
        $c = $this->build([
            'exists' => \DI\value(true),
        ]);

        $this->assertTrue($c->has('exists'));
    }

    // ─── Scoped + PHP-DI features combined ───

    #[Test]
    public function scopedWithDiAutowire(): void
    {
        $c = $this->build([
            SimpleDep::class => singleton(),
            ServiceWithDep::class => scoped(),
        ]);

        /** @var ServiceWithDep $a */
        $a = $c->get(ServiceWithDep::class);

        $this->provider->newContext();

        /** @var ServiceWithDep $b */
        $b = $c->get(ServiceWithDep::class);

        // Different instances across contexts
        $this->assertNotSame($a, $b);

        // But same singleton dependency
        $this->assertSame($a->dep, $b->dep);
    }

    #[Test]
    public function singletonWithDiFactory(): void
    {
        $counter = 0;
        $c = $this->build([
            'counted' => singleton(function () use (&$counter) {
                $counter++;

                return new stdClass();
            }),
        ]);

        $c->get('counted');
        $c->get('counted');
        $this->provider->newContext();
        $c->get('counted');

        $this->assertSame(1, $counter, 'Singleton factory should only run once');
    }

    #[Test]
    public function transientWithDiFactory(): void
    {
        $counter = 0;
        $c = $this->build([
            'counted' => transient(function () use (&$counter) {
                $counter++;

                return new stdClass();
            }),
        ]);

        $c->get('counted');
        $c->get('counted');
        $c->get('counted');

        $this->assertSame(3, $counter, 'Transient factory should run every time');
    }

    // ─── Helper ───

    /**
     * @param array<string, mixed> $definitions
     */
    private function build(array $definitions): \PHPdot\Container\ScopedContainer
    {
        return (new ContainerBuilder())
            ->withContextProvider($this->provider)
            ->withScopeValidation(false)
            ->addDefinitions($definitions)
            ->build();
    }
}

// ─── Test fixtures ───

class AutowiredService {}

class NamedService
{
    public function __construct(
        public readonly string $name,
    ) {}
}

class SimpleDep {}

class ServiceWithDep
{
    public function __construct(
        public readonly SimpleDep $dep,
    ) {}
}

interface SimpleInterface {}

class SimpleImplementation implements SimpleInterface {}
