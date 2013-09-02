<?php

namespace Throttle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleMaxHeap extends \SplMaxHeap
{
    public function compare($value1, $value2) {
        return ($value1['count'] - $value2['count']);
    }
}

class SymbolsStatsCommand extends Command
{
    protected function configure()
    {
        $this->setName('symbols:stats')
            ->setDescription('Display statistics about symbol files')
            ->addOption(
                'unused',
                'u',
                InputOption::VALUE_NONE,
                'List unused symbol files'
            )
            ->addOption(
                'crashers',
                'c',
                InputOption::VALUE_NONE,
                'Only look at modules that caused crashes (Warning: This is slow)'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Number of most-frequent symbol files to display',
                10
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $table = $this->getHelperSet()->get('table');
        $table->setCellHeaderFormat('%s');
        $table->setCellRowFormat('%s');

        $moduleHeap = new ModuleMaxHeap;
        $topModules = array();

        $databaseModules = array();

        $query = null;
        if ($input->getOption('crashers')) {
            $query = $app['db']->executeQuery('SELECT COUNT(crash.id) as count, module.name, module.identifier FROM crash JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 JOIN module ON crash.id = module.crash AND frame.module = module.name AND module.identifier != \'000000000000000000000000000000000\' GROUP BY module.name, module.identifier');
        } else {
            $query = $app['db']->executeQuery('SELECT COUNT(crash) as count, name, identifier FROM module WHERE identifier != \'000000000000000000000000000000000\' GROUP BY name, identifier');
        }

        while (($module = $query->fetch()) !== false) {
            if (!isset($databaseModules[$module['name']])) {
                $databaseModules[$module['name']] = array();
            }

            $databaseModules[$module['name']][] = $module['identifier'];

            $moduleHeap->insert($module);
        }

        for ($i = 0; $i < $input->getOption('limit'); $i++) {
            $topModules[] = $moduleHeap->extract();
        }
        unset($moduleHeap);

        $filesystemModules = array();
        $stores = \Filesystem::listDirectory($app['root'] . '/symbols', false);
        foreach ($stores as $store) {
            $binaries = \Filesystem::listDirectory($app['root'] . '/symbols/' . $store, false);
            foreach ($binaries as $binary) {
                $identifiers = \Filesystem::listDirectory($app['root'] . '/symbols/' . $store . '/' . $binary, false);
                foreach ($identifiers as $identifier) {
                    if (!isset($filesystemModules[$binary])) {
                        $filesystemModules[$binary] = array();
                    }

                    $filesystemModules[$binary][] = $identifier;
                }
            }
        }
        unset($stores);

        $total = 0;
        $matched = 0;
        foreach ($databaseModules as $name => $identifiers) {
            if (!isset($filesystemModules[$name])) {
                $total += count($identifiers);
                continue;
            }

            $otherIdentifiers = $filesystemModules[$name];
            foreach ($identifiers as $identifier) {
                $total += 1;

                foreach ($otherIdentifiers as $otherIdentifier) {
                    if ($otherIdentifier == $identifier) {
                        $matched += 1;
                        break;
                    }
                }
            }
        }
        $percent = ($matched / $total) * 100;
        $output->writeln('Symbol file coverage: ' . $percent . '%');

        if ($input->getOption('unused')) {
            $total = 0;
            $matched = 0;
            $unusedSymbols = array();
            foreach ($filesystemModules as $name => $identifiers) {
                if (!isset($databaseModules[$name])) {
                    $unusedSymbols[] = $name;
                    $total += count($identifiers);
                    continue;
                }

                $otherIdentifiers = $databaseModules[$name];
                foreach ($identifiers as $identifier) {
                    $total += 1;

                    foreach ($otherIdentifiers as $otherIdentifier) {
                        if ($otherIdentifier == $identifier) {
                            $matched += 1;
                            break;
                        }
                    }
                }
            }
            $percent = ($matched / $total) * 100;
            $output->writeln('Unused symbol files: ' . (100 - $percent) . '%');

            $output->writeln('');

            $output->writeln('Completely unused symbol files:');
            $table->setHeaders(array());
            $stride = 0;
            $row = array();
            $table->setRows(array());
            foreach ($unusedSymbols as $name) {
                if ($stride == 4) {
                    $table->addRow($row);
                    $row = array();
                    $stride = 0;
                }

                $row[] = $name;
                $stride += 1;
            }
            $table->addRow($row);
            $table->render($output);
        }

        if ($input->getOption('limit') > 0) {
            $output->writeln('');

            $output->writeln('Most frequent modules:');
            $table->setHeaders(array('Crashes', 'Binary', 'Identifier', 'Symbols'));
            $table->setRows(array());
            foreach ($topModules as $module) {
                $found = false;

                if (isset($filesystemModules[$module['name']])) {
                    $identifiers = $filesystemModules[$module['name']];
                    foreach ($identifiers as $identifier) {
                        if ($identifier == $module['identifier']) {
                            $found = true;
                            break;
                        }
                    }
                }

                $table->addRow(array($module['count'], $module['name'], $module['identifier'], ($found ? 'YES' : 'NO')));
            }
            $table->render($output);
        }
    }
}

