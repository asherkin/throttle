<?php

namespace Throttle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCommand extends Command
{
    protected function configure()
    {
        $this->setName('process')
            ->setDescription('Process pending crash dumps')
            ->addArgument(
                'limit',
                InputArgument::OPTIONAL,
                'Max dumps to process',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $limit = $input->getArgument('limit');

        if ($limit !== null && !ctype_digit($limit)) {
            throw new \InvalidArgumentException('\'limit\' must be an integer');
        }

        $lock = \PhutilFileLock::newForPath($app['root'] . '/cache/process.lck');
        $lock->lock();

        $pending = $app['db']->executeQuery('SELECT COALESCE(COUNT(id), 0) FROM crash WHERE processed = 0')->fetchColumn(0);

        $output->writeln('Found ' . $pending . ' pending crash dump(s)');

        if ($pending == 0) {
            return;
        } elseif ($limit !== null && $pending > $limit) {
            $pending = $limit;
            $output->writeln('Only processing the first ' . $limit);
        }

        $symbols = \Filesystem::listDirectory($app['root'] . '/symbols');
        $output->writeln('Using symbols from: ' . implode(' ', $symbols));

        foreach ($symbols as &$path) {
            $path = $app['root'] . '/symbols/' . $path;
        }
        unset($path);

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $pending);

        for ($count = 0; $count < $pending; $count++) {
            $app['db']->transactional(function($db) use ($app, $symbols) {
                $id = $app['db']->executeQuery('SELECT id FROM crash WHERE processed = 0 LIMIT 1')->fetchColumn(0);
                $minidump = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

                $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s %Ls', $minidump, $symbols);

                $crashThread = -1;
                $foundStack = false;

                foreach (new \LinesOfALargeExecFuture($future) as $line) {
                    $data = str_getcsv($line, '|');

                    if (!$foundStack) {
                        if ($line == '') {
                            $foundStack = true;
                        } elseif ($data[0] == 'Crash' && $data[3] !== '') {
                            $crashThread = $data[3];
                        } elseif ($data[0] == 'Module' && $data[3] !== '' && $data[4] !== '') {
                            $app['db']->executeUpdate('INSERT IGNORE INTO module VALUES (?, ?, ?)', array($id, $data[3], $data[4]));
                        }

                        continue;
                    }

                    $app['db']->executeUpdate('INSERT INTO frame VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array($id, $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]));
                }

                $future = new \ExecFuture($app['root'] . '/bin/minidump_comment %s', $minidump);

                $foundCmdline = false;
                $cmdline = '';

                foreach (new \LinesOfALargeExecFuture($future) as $line) {
                    if (!$foundCmdline) {
                        if (stripos($line, 'MD_LINUX_CMD_LINE') !== FALSE) {
                            $foundCmdline = true;
                        }

                        continue;
                    }

                    if ($line == '') {
                        break;
                    }

                    $cmdline .= $line;
                }

                $cmdline = trim(str_replace('\\0', ' ', $cmdline));

                $app['db']->executeUpdate('UPDATE crash SET cmdline = ?, thread = ?, processed = TRUE WHERE id = ?', array($cmdline, $crashThread, $id));
            });

            $progress->advance();
        }

        $progress->finish();

        $lock->unlock();
    }
}

