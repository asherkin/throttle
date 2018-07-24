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

        try {
            $lock->lock();
        } catch (\PhutilLockException $e) {
            $output->writeln('Lock still held, exiting.');
            return;
        }

        if ($input->getOption('update')) {
            $outdated = 0;
            $reprocess = $app['db']->executeQuery('SELECT DISTINCT crash FROM module WHERE processed = FALSE AND present = TRUE LIMIT 100');

            while (($id = $reprocess->fetchColumn(0)) !== false) {
                $app['db']->transactional(function($db) use ($id) {
                    $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
                    $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));
                    $db->executeUpdate('DELETE FROM crashnotice WHERE crash = ?', array($id));

                    $db->executeUpdate('UPDATE crash SET thread = NULL, processed = FALSE WHERE id = ?', array($id));
                });

                $outdated += 1;
            }

            if ($outdated > 0) {
                $app['redis']->hIncrBy('throttle:stats', 'crashes:needs-reprocessing', $outdated);
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

        $symbolCacheDirectory = \Filesystem::createDirectory($app['root'] . '/cache/symbols', 0777, true);

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $pending);

        $symbolCache = null;
        $repoCache = null;

        list($repoCacheJson, $symbolCacheJson) = $app['redis']->mGet(array('throttle:cache:repo', 'throttle:cache:symbol'));

        if ($repoCacheJson) {
            $repoCache = json_decode($repoCacheJson, true);
        }

        if ($symbolCacheJson) {
            $symbolCache = json_decode($symbolCacheJson, true);
        }

        if (!$repoCache) {
            $repoCache = array();
        }

        if (!$symbolCache) {
            $symbolCache = array();
        }

        $output->writeln('Loaded ' . count($repoCache) . ' repo cache entries and ' . count($symbolCache) . ' symbol cache entries.');

        for ($count = 0; $count < $pending; $count++) {
            $app['db']->transactional(function($db) use ($app, $symbols, $symbolCacheDirectory, &$symbolCache, &$repoCache) {
                $id = $app['db']->executeQuery('SELECT id FROM crash WHERE processed = 0 ORDER BY timestamp DESC LIMIT 1')->fetchColumn(0);
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

                            $cacheKey = $data[1] . '-' . $data[3] . '-' . $data[4];

                            if (array_key_exists($cacheKey, $symbolCache)) {
                                if ($symbolCache[$cacheKey]) {
                                    $app['redis']->hIncrBy('throttle:stats', 'symbols:found-cached', 1);
                                } else {
                                    $app['redis']->hIncrBy('throttle:stats', 'symbols:missing-cached', 1);
                                }

                                continue;
                            }

                            $symname = $data[3];
                            if (stripos($symname, '.pdb') == strlen($symname) - 4) {
                                $symname = substr($symname, 0, -4);
                            }

                            $symdir = $data[3] . '/' . $data[4];
                            $sympath = $symdir . '/' . $symname . '.sym';

                            if (file_exists($symbolCacheDirectory . '/' . $sympath)) {
                                $symbolCache[$cacheKey] = true;

                                $app['redis']->hIncrBy('throttle:stats', 'symbols:found', 1);

                                continue;
                            }

                            $foundSymbolFile = false;
                            foreach ($symbols as $path) {
                                if (file_exists($path . '/' . $sympath . '.gz')) {
                                    $foundSymbolFile = true;
                                    \Filesystem::createDirectory($symbolCacheDirectory . '/' . $symdir, 0777, true);
                                    \Filesystem::writeFile($symbolCacheDirectory . '/' . $sympath, gzdecode(\Filesystem::readFile($path . '/' . $sympath . '.gz')));
                                    break;
                                }
                            }

                            $symbolCache[$cacheKey] = $foundSymbolFile;

                            if ($foundSymbolFile) {
                                $app['redis']->hIncrBy('throttle:stats', 'symbols:found-compressed', 1);
                            } else {
                                $app['redis']->hIncrBy('throttle:stats', 'symbols:missing', 1);
                            }

                            continue;
                        }

                        $thread = (int)$data[0];
                        $frame = (int)$data[1];
                        $address = hexdec($data[6]);

                        if (!isset($addresses[$thread])) $addresses[$thread] = array();
                        $addresses[$thread][$frame] = $address;
                    }

                    $future = new \ExecFuture($app['root'] . '/bin/minidump_stackwalk -m %s %s 2>> %s', $minidump, $symbolCacheDirectory, $logs);

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

                                $cacheKey = $data[1] . '-' . $data[3] . '-' . $data[4];

                                $hasSymbols = ($symbolCache[$cacheKey] === true);

                                $app['db']->executeUpdate('INSERT IGNORE INTO module VALUES (?, ?, ?, ?, ?, ?)', array($id, $data[3], $data[4], $hasSymbols, $hasSymbols, hexdec($data[5])));

                                if ($hasSymbols) {
                                    if (!array_key_exists($cacheKey, $repoCache)) {
                                        $sympath = $symbolCacheDirectory . '/' . $data[3] . '/' . $data[4] . '/' . $symname . '.sym';
                                        $symbols = fopen($sympath, 'r');

                                        if (!$symbols) {
                                            var_dump($symbolCache);
                                            var_dump(array('cache' => $symbolCache[$cacheKey], 'cache_key' => $cacheKey, 'has_symbols' => $hasSymbols));
                                            throw new \Exception();
                                        }

                                        $repos = array();
                                        while (($record = fgets($symbols)) !== false) {
                                            $record = explode(' ', trim($record), 5);
                                            if ($record[0] === 'INFO' && $record[1] === 'REPO') {
                                                $repos[$record[4]] = array('url' => $record[3], 'rev' => $record[2]);
                                            }
                                        }
                                        krsort($repos);

                                        fclose($symbols);

                                        $repoCache[$cacheKey] = $repos;

                                        $app['redis']->hIncrBy('throttle:stats', 'symbols:repo-cache:miss', 1);

                                        //print('Cache MISS for ' . $cacheKey . ' (' . array_key_exists($cacheKey, $repoCache) . ') (' . count($repos) . ')' . PHP_EOL);
                                    } else {
                                        $app['redis']->hIncrBy('throttle:stats', 'symbols:repo-cache:hit', 1);

                                        //print('Cache HIT for ' . $cacheKey . PHP_EOL);
                                    }

                                    if (!empty($repoCache[$cacheKey])) {
                                        //print($cacheKey . ' matched crash dump.' . PHP_EOL);
                                        $moduleRepos[$data[1]] = $repoCache[$cacheKey];
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
                                //print($prefix . ' - ' . $repo['url'] . PHP_EOL);
                                if (substr($data[4], 0, strlen($prefix)) !== $prefix) {
                                    continue;
                                }

                                $github = 'https://github.com/';
                                $url = str_replace('git@github.com:', $github, $repo['url']);
                                if (substr($url, 0, strlen($github)) !== $github) {
                                    continue;
                                }
                                if (substr($url, -4) === '.git') {
                                    $url = substr($url, 0, -4);
                                }

                                $path = str_replace('\\', '/', substr($data[4], strlen($prefix)));
                                $url .= '/blob/' . $repo['rev'] . $path . '#L' . $data[5];

                                break;
                            }
                        }

                        // This stuff unfortunately doesn't work as the stack walker can get a completely different stack with symbols.
                        // Need to think about it when doing the rewrite.
                        $address = null;//$addresses[(int)$data[0]][(int)$data[1]];
                        //print_r(array($data[0], $data[1], $addresses[(int)$data[0]][(int)$data[1]]));
                        $app['db']->executeUpdate('INSERT INTO frame VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array($id, $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $rendered, $url, $address));
                    }
                } catch (\CommandException $e) {
                    $app['db']->executeUpdate('UPDATE crash SET processed = TRUE, failed = TRUE WHERE id = ?', array($id));

                    $app['redis']->hIncrBy('throttle:stats', 'crashes:failed', 1);

                    return;
                } finally {
                    \Filesystem::writeFile($logs . '.gz', gzencode(str_replace($app['root'], '', \Filesystem::readFile($logs))));
                    \Filesystem::remove($logs);
                }

                $app['db']->executeUpdate('UPDATE crash SET thread = ?, processed = TRUE WHERE id = ?', array($crashThread, $id));

                $app['db']->executeUpdate('UPDATE crash SET stackhash = (SELECT GROUP_CONCAT(SUBSTRING(SHA2(rendered, 256), 1, 8) ORDER BY frame ASC SEPARATOR \'\') AS hash FROM frame WHERE crash = ? AND thread = ? AND frame < 10 AND module != \'\' GROUP BY crash, thread) WHERE id = ?', array($id, $crashThread, $id));

                $app['redis']->hIncrBy('throttle:stats', 'crashes:processed', 1);

                // This isn't as important, so do it after we mark the crash as processed.
                // TODO: We're in a transaction... the above comment makes no sense.
                $rules = $app['db']->executeQuery('SELECT rule FROM notice');

                $count = 0;
                $query = 'INSERT IGNORE INTO crashnotice SELECT :crash AS crash, notice FROM (';
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

        // Upload the caches to redis to use for the next run.
        $ttl = $app['redis']->ttl('throttle:cache:symbol');
        if ($ttl <= 0) {
            $ttl = 1800;
        }

        $app['redis']->setEx('throttle:cache:repo', $ttl, json_encode($repoCache));
        $app['redis']->setEx('throttle:cache:symbol', $ttl, json_encode($symbolCache));

        $progress->finish();

        $lock->unlock();
    }
}

