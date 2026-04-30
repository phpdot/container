<?php

declare(strict_types=1);

/**
 * `container:show` Command
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Cli\Command;

use PHPdot\Container\ScopedContainer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'container:show', description: 'Describe one container entry — scope, implementation, source.')]
final class ShowCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'The service ID to describe (e.g. "PHPdot\\\\Routing\\\\Router").');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->container instanceof ScopedContainer) {
            $output->writeln('<error>container:show requires a PHPdot\\Container\\ScopedContainer instance.</error>');

            return Command::FAILURE;
        }

        /** @var string $id */
        $id = $input->getArgument('id');

        if (!$this->container->has($id)) {
            $output->writeln(sprintf('<error>Container has no entry "%s".</error>', $id));
            $output->writeln('<comment>Run `container:list` to see all registered entries.</comment>');

            return Command::FAILURE;
        }

        $info = $this->container->describe($id);

        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $info['id']));
        $output->writeln(sprintf('  Scope:           <comment>%s</comment>', $info['scope']));
        $output->writeln(sprintf(
            '  Implementation:  <comment>%s</comment>',
            $info['implementation'] ?? '(self / autowire / factory)',
        ));

        if ($info['scope'] === 'SINGLETON' && $info['implementation'] === null) {
            $output->writeln('');
            $output->writeln('<comment>PHP-DI debug:</comment>');
            $output->writeln('  ' . str_replace("\n", "\n  ", $this->container->phpdi()->debugEntry($id)));
        }

        $output->writeln('');

        return Command::SUCCESS;
    }
}
