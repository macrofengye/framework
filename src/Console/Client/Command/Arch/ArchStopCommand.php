<?php

namespace Kraken\Console\Client\Command\Arch;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Kraken\Console\Client\ConsoleCommand;

class ArchStopCommand extends ConsoleCommand
{
    /**
     *
     */
    protected function config()
    {
        $this
            ->setName('arch:stop')
            ->setDescription('Stops part of architecture.')
        ;

        $this->addArgument(
            'alias',
            InputArgument::REQUIRED,
            'Alias of root container to be stoppped.'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed[]
     */
    protected function command(InputInterface $input, OutputInterface $output)
    {
        $alias  = $input->getArgument('alias');

        $cmd  = 'arch:stop';
        $opts = [];

        return [ $alias, $cmd, $opts ];
    }
}