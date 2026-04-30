<?php

declare(strict_types=1);

/**
 * `container:list` Command
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
namespace PHPdot\Container\Cli\Command;

use PHPdot\Container\ScopedContainer;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'container:list', description: 'List every entry registered in the live container.')]
final class ListCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->container instanceof ScopedContainer) {
            $output->writeln('<error>container:list requires a PHPdot\\Container\\ScopedContainer instance.</error>');

            return Command::FAILURE;
        }

        $entries = $this->container->entries();

        $output->writeln('');
        $output->writeln(sprintf('<info>%d entries</info>', count($entries)));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Scope', 'ID', 'Resolves to']);

        foreach ($entries as $id) {
            $info = $this->container->describe($id);

            $resolvesTo = $info['implementation'] ?? '<comment>(self / autowire / factory)</comment>';

            $table->addRow([$info['scope'], $id, $resolvesTo]);
        }

        $table->render();

        $output->writeln('');

        return Command::SUCCESS;
    }
}
