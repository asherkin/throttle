<?php

namespace Throttle\Command;

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
        $count = $query->rowCount();

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $count);

        while (($module = $query->fetch()) !== false) {
            $found = false;

            $symname = $module['name'];
            if (stripos($symname, '.pdb') == strlen($symname) - 4) {
                $symname = substr($symname, 0, -4);
            }

            foreach ($symbols as $path) {
                if (file_exists($app['root'] . '/symbols/' . $path . '/' . $module['name'] . '/' . $module['identifier'] . '/' . $symname . '.sym.gz')) {
                    $found = true;
                    break;
                }
            }

            $progress->advance();

            if (!$found && !$input->getOption('clean')) {
                continue;
            }

            $app['db']->executeUpdate('UPDATE module SET present = ? WHERE name = ? AND identifier = ?', array($found, $module['name'], $module['identifier']));
        }

        $progress->finish();

        $output->writeln('Waiting for processing lock...');

        $lock = \PhutilFileLock::newForPath($app['root'] . '/cache/process.lck');
        $lock->lock(300);

        try {
            $redis = new \Redis();
            $redis->pconnect('127.0.0.1', 6379, 1);

            $redis->del('throttle:cache:symbol');

            $redis->close();
        } catch (\Exception $e) {}

        $output->writeln('Flushed symbol cache');

        $lock->unlock();
    }
}

