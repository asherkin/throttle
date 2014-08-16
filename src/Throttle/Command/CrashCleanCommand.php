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

        $groups = $app['db']->executeQuery('SELECT owner, ip FROM crash WHERE processed = 1 GROUP BY owner, ip HAVING COUNT(*) > 100');

        while ($group = $groups->fetch()) {
            $query = $app['db']->executeQuery('SELECT id FROM crash WHERE owner = ? AND ip = ? AND processed = 1 ORDER BY timestamp DESC LIMIT 1000 OFFSET 100', array($group['owner'], $group['ip']));

            $crashes = array();
            while ($id = $query->fetchColumn(0)) {
                $crashes[] = $id;
            }

            if ($input->getOption('dry-run')) {
                $count = count($crashes);
            } else {
                $count = $app['db']->executeUpdate('DELETE FROM crash WHERE id IN (?) LIMIT 1000', array($crashes), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
            }

            $output->writeln('Deleted ' . $count . ' crash dumps for server: ' . $group['owner'] . ', ' . long2ip($group['ip']));
        }
    }
}

