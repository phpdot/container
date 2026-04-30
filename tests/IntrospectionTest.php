<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scope;

use function PHPdot\Container\scoped;
use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;

use function PHPdot\Container\transient;

use PHPUnit\Framework\TestCase;
use stdClass;

final class IntrospectionTest extends TestCase
{
    public function testEntriesListsAllRegisteredIds(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitions([
                'svc.singleton' => singleton(static fn() => new stdClass()),
                'svc.scoped'    => scoped(static fn() => new stdClass()),
                'svc.transient' => transient(static fn() => new stdClass()),
            ])
            ->build();

        $entries = $container->entries();

        self::assertContains('svc.singleton', $entries);
        self::assertContains('svc.scoped', $entries);
        self::assertContains('svc.transient', $entries);
    }

    public function testEntriesAreSortedAlphabetically(): void
    {
        $container = (new ContainerBuilder())
            ->addDefinitions([
                'zebra'    => singleton(static fn() => new stdClass()),
                'alpha'    => singleton(static fn() => new stdClass()),
                'mid.svc'  => singleton(static fn() => new stdClass()),
            ])
            ->build();

        $entries = $container->entries();

        $userEntries = array_filter($entries, static fn(string $id): bool => in_array($id, ['zebra', 'alpha', 'mid.svc'], true));
        $userEntries = array_values($userEntries);

        self::assertSame(['alpha', 'mid.svc', 'zebra'], $userEntries);
    }

    public function testDescribeIdentifiesScopeForSingleton(): void
    {
        $container = (new ContainerBuilder())
            ->addDefinitions(['my.id' => singleton(static fn() => new stdClass())])
            ->build();

        $info = $container->describe('my.id');

        self::assertSame('my.id', $info['id']);
        self::assertSame('SINGLETON', $info['scope']);
        self::assertNull($info['implementation']);
    }

    public function testDescribeIdentifiesScopeForScoped(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitions(['my.id' => scoped(static fn() => new stdClass())])
            ->build();

        $info = $container->describe('my.id');

        self::assertSame('SCOPED', $info['scope']);
    }

    public function testDescribeIdentifiesScopeForTransient(): void
    {
        $container = (new ContainerBuilder())
            ->addDefinitions(['my.id' => transient(static fn() => new stdClass())])
            ->build();

        $info = $container->describe('my.id');

        self::assertSame('TRANSIENT', $info['scope']);
    }

    public function testDescribeReportsImplementationForOverriddenBinding(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitions([
                'iface' => new ScopedDefinition(scope: Scope::SCOPED, implementation: stdClass::class),
            ])
            ->build();

        $info = $container->describe('iface');

        self::assertSame(stdClass::class, $info['implementation']);
    }

    public function testEntriesIncludesPhpDiBuiltins(): void
    {
        $container = (new ContainerBuilder())->build();

        $entries = $container->entries();

        self::assertContains(\Psr\Container\ContainerInterface::class, $entries);
    }
}
