<?php

namespace Throttle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrashCleanCommand extends Command
{
    protected function configure()
    {
        $this->setName('crash:clean')
            ->setDescription('Cleanup orphaned crash dumps.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only list orphan dumps, do not delete them.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $prefixes = \Filesystem::listDirectory($app['root'] . '/dumps', false);
        foreach ($prefixes as $prefix) {
            $dumps = \Filesystem::listDirectory($app['root'] . '/dumps/' . $prefix, false);
            foreach ($dumps as $dump) {
                $id = substr($dump, 0, -4);
                $present = $app['db']->executeQuery('SELECT id FROM crash WHERE id = ? LIMIT 1', array($id))->fetchColumn(0) !== false;

                if ($present) {
                    continue;
                }

                if ($input->getOption('dry-run')) {
                    $output->writeln($id);
                    continue;
                }

                \Filesystem::remove($app['root'] . '/dumps/' . $prefix . '/' . $dump);
            }
        }
    }
}

