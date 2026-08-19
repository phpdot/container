<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Exception\ContainerException;
use PHPdot\Container\Exception\NotFoundException;

use function PHPdot\Container\scoped;

use PHPdot\Container\ScopedContainer;
use PHPdot\Container\Testing\TestContextProvider;
use PHPdot\Container\Validation\ScopeMismatchException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * PSR-11 §3: exceptions thrown by a ContainerInterface implementation must
 * implement ContainerExceptionInterface, with NotFoundExceptionInterface for
 * identifiers that cannot exist. Pins the package hierarchy to both
 * interfaces, and to RuntimeException for pre-hierarchy catch sites.
 */
final class Psr11ComplianceTest extends TestCase
{
    private TestContextProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestContextProvider();
    }

    #[Test]
    public function unknownClassResolutionThrowsNotFound(): void
    {
        $container = $this->build([
            'App\Missing\DoesNotExist' => scoped(),
        ]);

        try {
            $container->get('App\Missing\DoesNotExist');
            self::fail('resolving a nonexistent class must throw');
        } catch (NotFoundException $e) {
            self::assertInstanceOf(NotFoundExceptionInterface::class, $e);
            self::assertInstanceOf(ContainerExceptionInterface::class, $e);
        }
    }

    #[Test]
    public function autowireFailureThrowsContainerException(): void
    {
        $container = $this->build([
            Psr11Unwirable::class => scoped(),
        ]);

        try {
            $container->get(Psr11Unwirable::class);
            self::fail('an unwirable parameter must throw');
        } catch (ContainerException $e) {
            self::assertInstanceOf(ContainerExceptionInterface::class, $e);
            self::assertNotInstanceOf(NotFoundExceptionInterface::class, $e);
        }
    }

    #[Test]
    public function hierarchyStaysCatchableAsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, new ContainerException('x'));
        self::assertTrue(is_subclass_of(ScopeMismatchException::class, ContainerExceptionInterface::class));
    }

    /**
     * @param array<string, mixed> $definitions
     *
     * @return ScopedContainer
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

final class Psr11Unwirable
{
    public function __construct(
        public int $countWithoutDefault,
    ) {}
}
