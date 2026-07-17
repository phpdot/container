<?php

declare(strict_types=1);
namespace PHPdot\Container\Tests\Cli\Command;

use PHPdot\Container\Cli\Command\ListCommand;
use PHPdot\Container\ContainerBuilder;

use function PHPdot\Container\singleton;

use PHPdot\Container\Testing\TestContextProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;

use Symfony\Component\Console\Tester\CommandTester;

final class ListCommandTest extends TestCase
{
    #[Test]
    public function listsAllRegisteredEntries(): void
    {
        $container = (new ContainerBuilder())
            ->withContextProvider(new TestContextProvider())
            ->addDefinitions([
                'svc.alpha' => singleton(static fn(): stdClass => new stdClass()),
                'svc.beta'  => singleton(static fn(): stdClass => new stdClass()),
            ])
            ->build();

        $tester = new CommandTester(new ListCommand($container));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('svc.alpha', $output);
        self::assertStringContainsString('svc.beta', $output);
        self::assertStringContainsString('SINGLETON', $output);
    }

    #[Test]
    public function reportsEntryCount(): void
    {
        $container = (new ContainerBuilder())
            ->addDefinitions([
                'svc.one' => singleton(static fn(): stdClass => new stdClass()),
            ])
            ->build();

        $tester = new CommandTester(new ListCommand($container));
        $tester->execute([]);

        self::assertMatchesRegularExpression('/\d+ entries/', $tester->getDisplay());
    }
}
