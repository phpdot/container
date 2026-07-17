<?php

declare(strict_types=1);

namespace PHPdot\Container\Tests\Cli\Command;

use PHPdot\Container\Cli\Command\ShowCommand;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Definition\ScopedDefinition;
use PHPdot\Container\Scope;
use PHPdot\Container\Testing\TestContextProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function PHPdot\Container\singleton;

final class ShowCommandTest extends TestCase
{
    #[Test]
    public function showsRegisteredEntry(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitions([
                'svc.alpha' => singleton(static fn (): stdClass => new stdClass()),
            ])
            ->build();

        $tester = new CommandTester(new ShowCommand($container));
        $tester->execute(['id' => 'svc.alpha']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('svc.alpha', $output);
        self::assertStringContainsString('SINGLETON', $output);
    }

    #[Test]
    public function showsImplementationForAliasedBinding(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitions([
                'iface' => new ScopedDefinition(scope: Scope::SCOPED, implementation: stdClass::class),
            ])
            ->build();

        $tester = new CommandTester(new ShowCommand($container));
        $tester->execute(['id' => 'iface']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('iface', $output);
        self::assertStringContainsString('SCOPED', $output);
        self::assertStringContainsString(stdClass::class, $output);
    }

    #[Test]
    public function returnsFailureForUnknownEntry(): void
    {
        $container = (new ContainerBuilder())->build();

        $tester = new CommandTester(new ShowCommand($container));
        $tester->execute(['id' => 'does.not.exist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Container has no entry', $tester->getDisplay());
    }
}
