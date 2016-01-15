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
            ->setDescription('Cleanup old crash dumps.')
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

        $groups = $app['db']->executeQuery('SELECT owner, ip FROM crash GROUP BY owner, ip HAVING COUNT(*) > 100');

        while ($group = $groups->fetch()) {
            if ($group['owner'] !== null) {
                $query = $app['db']->executeQuery('SELECT id FROM crash WHERE owner = ? AND ip = ? ORDER BY timestamp DESC LIMIT 100 OFFSET 100', array($group['owner'], $group['ip']));
            } else {
                $query = $app['db']->executeQuery('SELECT id FROM crash WHERE owner IS NULL AND ip = ? ORDER BY timestamp DESC LIMIT 100 OFFSET 100', array($group['ip']));
            }

            $crashes = array();
            while ($id = $query->fetchColumn(0)) {
                $crashes[] = $id;
            }

            if ($input->getOption('dry-run')) {
                $count = count($crashes);
            } else {
                $count = $app['db']->executeUpdate('DELETE FROM crash WHERE id IN (?) LIMIT 100', array($crashes), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
            }

            $output->writeln('Deleted ' . $count . ' crash dumps for server: ' . $group['owner'] . ', ' . long2ip($group['ip']));
        }

        if ($input->getOption('dry-run')) {
            return;
        }

        $count = $app['db']->executeUpdate('DELETE FROM crash WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 100');
        if ($count > 0) {
            $output->writeln('Deleted ' . $count . ' old crash dumps.');
        }

        $query = $app['db']->executeQuery('SELECT id FROM crash');

        $crashes = array();
        while ($id = $query->fetchColumn(0)) {
            $crashes[$id] = true;
        }

        $count = 0;
        $buckets = \Filesystem::listDirectory($app['root'] . '/dumps', false);
        foreach ($buckets as $bucket) {
            $dumps = \Filesystem::listDirectory($app['root'] . '/dumps/' . $bucket, false);
            foreach ($dumps as $dump) {
                $filename = explode('.', $dump, 2);
                
		if (isset($crashes[$filename[0]])) {
                    continue;
                }

                $path = $app['root'] . '/dumps/' . $bucket . '/' . $dump;
                \Filesystem::remove($path);

                $count++;
            }
        }

        if ($count > 0) {
            $output->writeln('Deleted ' . $count . ' orphan crash dumps.');
        }
    }
}

