<?php

namespace Throttle\Command;

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
            $reprocess = $app['db']->executeQuery('SELECT DISTINCT crash FROM module WHERE processed = FALSE AND present = TRUE LIMIT 100');

            while (($id = $reprocess->fetchColumn(0)) !== false) {
                $app['db']->transactional(function($db) use ($id) {
                    $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
                    $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));
                    $db->executeUpdate('DELETE FROM crashnotice WHERE crash = ?', array($id));

                    $db->executeUpdate('UPDATE crash SET cmdline = NULL, thread = NULL, processed = FALSE WHERE id = ?', array($id));
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

        $symbolCache = \Filesystem::createDirectory($app['root'] . '/cache/symbols', 0777, true);

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $pending);

        for ($count = 0; $count < $pending; $count++) {
            $app['db']->transactional(function($db) use ($app, $symbols, $symbolCache) {
                $id = $app['db']->executeQuery('SELECT id FROM crash WHERE processed = 0 LIMIT 1')->fetchColumn(0);
                $minidump = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';
                $logs = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.txt';

                try {
                    $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s 2> %s', $minidump, $logs);

                    foreach (new \LinesOfALargeExecFuture($future) as $line) {
                        $data = str_getcsv($line, '|');

                        if ($data[0] != 'Module' || $data[3] === '' || $data[4] === '') {
                            continue;
                        }

                        $symname = $data[3];
                        if (stripos($symname, '.pdb') == strlen($symname) - 4) {
                            $symname = substr($symname, 0, -4);
                        }

                        $symdir = $data[3] . '/' . $data[4];
                        $sympath = $symdir . '/' . $symname . '.sym';

                        if (file_exists($symbolCache . '/' . $sympath)) {
                            continue;
                        }

                        foreach ($symbols as $path) {
                            if (file_exists($path . '/' . $sympath . '.gz')) {
                                \Filesystem::createDirectory($symbolCache . '/' . $symdir, 0777, true);
                                \Filesystem::writeFile($symbolCache . '/' . $sympath, gzdecode(str_replace($app['root'], '', \Filesystem::readFile($path . '/' . $sympath . '.gz'))));
                                break;
                            }
                        }
                    }

                    $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s %s 2>> %s', $minidump, $symbolCache, $logs);

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
                                $symname = $data[3];
                                if (stripos($symname, '.pdb') == strlen($symname) - 4) {
                                    $symname = substr($symname, 0, -4);
                                }

                                $sympath = $data[3] . '/' . $data[4] . '/' . $symname . '.sym';

                                $hasSymbols = file_exists($symbolCache . '/' . $sympath);

                                $app['db']->executeUpdate('INSERT IGNORE INTO module VALUES (?, ?, ?, ?, ?)', array($id, $data[3], $data[4], $hasSymbols, $hasSymbols));
                            }

                            continue;
                        }

                        if ($data[0] != $crashThread && $data[0] != '0') {
                            continue;
                        }

                        $rendered = $data[6];
                        if ($data[4] != '') {
                            $data[4] = basename(str_replace('\\', '/', $data[4]));
                            $rendered = $data[2] . '!' . $data[3] . ' [' . $data[4] . ':' . $data[5] . ' + ' . $data[6] . ']';
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
                    $app['db']->executeUpdate('UPDATE crash SET processed = TRUE, failed = TRUE WHERE id = ?', array($id));

                    return;
                } finally {
                    \Filesystem::writeFile($logs . '.gz', gzencode(str_replace($app['root'], '', \Filesystem::readFile($logs))));
                    \Filesystem::remove($logs);
                }

                $app['db']->executeUpdate('UPDATE crash SET cmdline = ?, thread = ?, processed = TRUE WHERE id = ?', array($cmdline, $crashThread, $id));

                // This isn't as important, so do it after we mark the crash as processed.
                $rules = $app['db']->executeQuery('SELECT rule FROM notice');

                $count = 0;
                $query = 'INSERT INTO crashnotice SELECT :crash AS crash, notice FROM (';
                while (($rule = $rules->fetchColumn(0)) !== false) {
                    $query .= $rule . ' UNION ALL ';
                    $count++;
                }
                $query = substr($query, 0, -strlen(' UNION ALL ')) . ') AS notices';

                if ($count > 0) {
                    $app['db']->executeUpdate($query, array('crash' => $id));
                }
            });

            $progress->advance();
        }

        $progress->finish();

        $lock->unlock();
    }
}

