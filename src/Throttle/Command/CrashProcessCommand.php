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

        $repoCache = array();

        for ($count = 0; $count < $pending; $count++) {
            $app['db']->transactional(function($db) use ($app, $symbols, $symbolCache, &$repoCache) {
                $id = $app['db']->executeQuery('SELECT id FROM crash WHERE processed = 0 LIMIT 1')->fetchColumn(0);
                $minidump = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';
                $logs = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.txt';

                try {
                    $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s 2> %s', $minidump, $logs);

                    $foundStack = false;
                    $addresses = array();

                    foreach (new \LinesOfALargeExecFuture($future) as $line) {
                        $data = str_getcsv($line, '|');

                        if (!$foundStack) {
                            if ($line == '') {
                                $foundStack = true;
                                continue;
                            }

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
                                    \Filesystem::writeFile($symbolCache . '/' . $sympath, gzdecode(\Filesystem::readFile($path . '/' . $sympath . '.gz')));
                                    break;
                                }
                            }

                            continue;
                        }

                        $thread = (int)$data[0];
                        $frame = (int)$data[1];
                        $address = hexdec($data[6]);

                        if (!isset($addresses[$thread])) $addresses[$thread] = array();
                        $addresses[$thread][$frame] = $address;
                    }

                    $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s %s 2>> %s', $minidump, $symbolCache, $logs);

                    $crashThread = -1;
                    $foundStack = false;
                    $moduleRepos = array();

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

                                $sympath = $symbolCache . '/' . $data[3] . '/' . $data[4] . '/' . $symname . '.sym';

                                $hasSymbols = file_exists($sympath);

                                $app['db']->executeUpdate('INSERT IGNORE INTO module VALUES (?, ?, ?, ?, ?, ?)', array($id, $data[3], $data[4], $hasSymbols, $hasSymbols, hexdec($data[5])));

                                if ($hasSymbols) {
                                    $repoCacheKey = $data[1] . '-' . $data[3] . '-' . $data[4];

                                    if (!array_key_exists($repoCacheKey, $repoCache)) {
                                        $symbols = fopen($sympath, 'r');

                                        $repos = array();
                                        while (($record = fgets($symbols)) !== false) {
                                            $record = explode(' ', $record, 5);
                                            if ($record[0] === 'INFO' && $record[1] === 'REPO') {
                                                $repos[$record[4]] = array('url' => $record[3], 'rev' => $record[2]);
                                            }
                                        }
                                        krsort($repos);

                                        fclose($symbols);

                                        $repoCache[$repoCacheKey] = $repos;

                                        //print('Cache MISS for ' . $repoCacheKey . ' (' . array_key_exists($repoCacheKey, $repoCache) . ')' . PHP_EOL);
                                    } else {
                                        //print('Cache HIT for ' . $repoCacheKey . PHP_EOL);
                                    }

                                    if (!empty($repoCache[$repoCacheKey])) {
                                        $moduleRepos[$data[1]] = $repoCache[$repoCacheKey];
                                    }
                                }
                            }

                            continue;
                        }

                        if ($data[0] != $crashThread && $data[0] != '0') {
                            continue;
                        }

                        $rendered = $data[6];
                        if ($data[4] != '') {
                            $filename = basename(str_replace('\\', '/', $data[4]));
                            $rendered = $data[2] . '!' . $data[3] . ' [' . $filename . ':' . $data[5] . ' + ' . $data[6] . ']';
                        } else if ($data[3] != '') {
                            $rendered = $data[2] . '!' . $data[3] . ' + ' . $data[6];
                        } else if ($data[2] != '') {
                            $rendered = $data[2] . ' + ' . $data[6];
                        }

                        $url = null;
                        if ($data[4] != '' && array_key_exists($data[2], $moduleRepos)) {
                            foreach ($moduleRepos[$data[2]] as $prefix => $repo) {
                                if (substr($data[4], 0, strlen($prefix)) !== $prefix) {
                                    continue;
                                }

                                $path = str_replace('\\', '/', substr($data[4], strlen($prefix)));
                                $url = $repo['url'] . '/blob/' . $repo['rev'] . $path . '#L' . $data[5];

                                break;
                            }
                        }

                        // This stuff unfortunately doesn't work as the stack walker can get a completely different stack with symbols.
                        // Need to think about it when doing the rewrite.
                        $address = null;//$addresses[(int)$data[0]][(int)$data[1]];
                        //print_r(array($data[0], $data[1], $addresses[(int)$data[0]][(int)$data[1]]));
                        $app['db']->executeUpdate('INSERT INTO frame VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($id, $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $rendered, $url, $address));
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

                $app['db']->executeUpdate('UPDATE crash SET stackhash = (SELECT GROUP_CONCAT(SUBSTRING(SHA2(rendered, 256), 1, 2) ORDER BY frame ASC SEPARATOR \'\') AS hash FROM frame WHERE crash = ? AND thread = ? AND frame < 10 AND module != \'\' GROUP BY crash, thread) WHERE id = ?', array($id, $crashThread, $id));

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

