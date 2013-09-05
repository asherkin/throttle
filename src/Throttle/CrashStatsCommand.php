<?php

namespace Throttle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrashStatsCommand extends Command
{
    protected function configure()
    {
        $this->setName('crash:stats')
            ->setDescription('Display statistics about crashes.')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Number of crashes to display',
                20
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $table = $this->getHelperSet()->get('table');
        $table->setCellHeaderFormat('%s');
        $table->setCellRowFormat('%s');

        $table->setHeaders(array('', 'Function'));

        $query = $app['db']->executeQuery('SELECT COUNT(frame.rendered) as count, frame.rendered FROM crash JOIN frame ON frame.crash = crash.id AND frame.thread = crash.thread AND frame.frame = 0 WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH) GROUP BY frame.rendered ORDER BY count DESC LIMIT ?', array($input->getOption('limit')), array(\PDO::PARAM_INT));
        while (($module = $query->fetch()) !== false) {
            $table->addRow($module);
        }

        $table->render($output);
    }
}

