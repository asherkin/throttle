<?php

namespace Throttle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SymbolsUpdateCommand extends Command
{
    protected function configure()
    {
        $this->setName('symbols:update')
            ->setDescription('Update module information in database.')
            ->addOption(
                'clean',
                'c',
                InputOption::VALUE_NONE,
                'Rebuild all module information rather than just missing'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $symbols = \Filesystem::listDirectory($app['root'] . '/symbols');
        $query = $app['db']->executeQuery('SELECT DISTINCT name, identifier FROM module WHERE identifier != \'000000000000000000000000000000000\'' . ($input->getOption('clean') ? '' : ' AND present = 0'));

        while (($module = $query->fetch()) !== false) {
            $found = false;

            $symname = $module['name'];
            if (stripos($symname, '.pdb') == strlen($symname) - 4) {
                $symname = substr($symname, 0, -4);
            }

            foreach ($symbols as $path) {
                if (file_exists($app['root'] . '/symbols/' . $path . '/' . $module['name'] . '/' . $module['identifier'] . '/' . $symname . '.sym')) {
                    $found = true;
                    break;
                }
            }

            if (!$found && !$input->getOption('clean')) {
                continue;
            }

            $app['db']->executeUpdate('UPDATE module SET present = ? WHERE name = ? AND identifier = ?', array($found, $module['name'], $module['identifier']));
        }
    }
}

