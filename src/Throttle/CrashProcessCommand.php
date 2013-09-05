<?php

namespace Throttle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrashProcessCommand extends Command
{
    protected function configure()
    {
        $this->setName('crash:process')
            ->setDescription('Process pending crash dumps.')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Max dumps to process'
            )
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                'Reprocess old dumps with new symbol files'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $limit = $input->getOption('limit');

        if ($limit !== null && !ctype_digit($limit)) {
            throw new \InvalidArgumentException('\'limit\' must be an integer');
        }

        $lock = \PhutilFileLock::newForPath($app['root'] . '/cache/process.lck');
        $lock->lock();

        if ($input->getOption('update')) {
            $outdated = 0;
            $reprocess = $app['db']->executeQuery('SELECT DISTINCT crash FROM module WHERE processed = FALSE AND present = TRUE');

            while (($id = $reprocess->fetchColumn(0)) !== false) {
                $app['db']->transactional(function($db) use ($id) {
                    $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
                    $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));

                    $db->executeUpdate('UPDATE crash SET cmdline = NULL, thread = NULL, output = NULL, processed = FALSE WHERE id = ?', array($id));
                });

                $outdated += 1;
            }

            $output->writeln('Found ' . $outdated . ' outdated crash dump(s)');
        }

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
                $logs = new \TempFile();

                try {
                    $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s %Ls 2> %s', $minidump, $symbols, $logs);

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
                                $hasSymbols = false;

                                $symname = $data[3];
                                if (stripos($symname, '.pdb') == strlen($symname) - 4) {
                                    $symname = substr($symname, 0, -4);
                                }

                                foreach ($symbols as $path) {
                                    if (file_exists($path . '/' . $data[3] . '/' . $data[4] . '/' . $symname . '.sym')) {
                                        $hasSymbols = true;
                                        break;
                                    }
                                }

                                $app['db']->executeUpdate('INSERT IGNORE INTO module VALUES (?, ?, ?, ?, ?)', array($id, $data[3], $data[4], $hasSymbols, $hasSymbols));
                            }

                            continue;
                        }

                        $rendered = $data[6];
                        if ($data[4] != '') {
                            $file = basename(str_replace('\\', '/', $data[4]));
                            $rendered = $data[2] . '!' . $data[3] . ' [' . $file . ':' . $data[5] . ' + ' . $data[6] . ']';
                        } else if ($data[3] != '') {
                            $rendered = $data[2] . '!' . $data[3] . ' + ' . $data[6];
                        } else if ($data[2] != '') {
                            $rendered = $data[2] . ' + ' . $data[6];
                        }

                        $app['db']->executeUpdate('INSERT INTO frame VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array($id, $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $rendered));
                    }

                    $future = new \ExecFuture($app['root'] . '/bin/minidump_comment %s 2>> %s', $minidump, $logs);

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
                } catch (\CommandException $e) {
                    $app['db']->executeUpdate('UPDATE crash SET output = ?, processed = TRUE, failed = TRUE WHERE id = ?', array(str_replace($app['root'], '', \Filesystem::readFile($logs)), $id));

                    return;
                }

                $app['db']->executeUpdate('UPDATE crash SET cmdline = ?, thread = ?, output = ?, processed = TRUE WHERE id = ?', array($cmdline, $crashThread, str_replace($app['root'], '', \Filesystem::readFile($logs)), $id));
            });

            $progress->advance();
        }

        $progress->finish();

        $lock->unlock();
    }
}

