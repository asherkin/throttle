<?php

namespace Throttle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SymbolsMozillaDownloadCommand extends Command
{
    protected function configure()
    {
        $this->setName('symbols:mozilla:download')
            ->setDescription('Download missing symbol files from the Mozilla Symbol Server.')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Module Name'
            )
            ->addArgument(
                'identifier',
                InputArgument::OPTIONAL,
                'Module Identifier'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $limit = $input->getOption('limit');

        if ($limit !== null && !ctype_digit($limit)) {
            throw new \InvalidArgumentException('\'limit\' must be an integer');
        }

        $manualName = $input->getArgument('name');
        $manualIdentifier = $input->getArgument('identifier');

        $modules = Array();

        if ($manualName) {
            if (!$manualIdentifier) {
                throw new \RuntimeException('Specifying \'name\' requires specifying \'identifier\' as well.');
            }

            $modules[] = Array('name' => $manualName, 'identifier' => $manualIdentifier);
        } else {
            $query = 'SELECT DISTINCT name, identifier FROM module WHERE present = 0';

            if ($limit !== null) {
                $query .= ' LIMIT ' . $limit;
            }

            $modules = $app['db']->executeQuery($query)->fetchAll();
        }

        $blacklist = null;

        $blacklistJson = $app['redis']->get('throttle:cache:blacklist:mozilla');

        if ($blacklistJson) {
            $blacklist = json_decode($blacklistJson, true);
        }

        if (!$blacklist) {
            $blacklist = array();
        }

        $output->writeln('Loaded ' . count($blacklist) . ' blacklist entries');

        $count = count($modules);
        $output->writeln('Found ' . $count . ' missing symbols');

        // Prepare HTTPSFutures for downloading compressed PDBs.
        $futures = array();
        foreach ($modules as $key => $module) {
            $name = $module['name'];
            $identifier = $module['identifier'];

            if (isset($blacklist[$name])) {
                if ($blacklist[$name]['_total'] >= 9) {
                    continue;
                }

                if (isset($blacklist[$name][$identifier])) {
                    if ($blacklist[$name][$identifier] >= 3) {
                        continue;
                    }
                }
            }

            $symname = $name;
            if (substr($symname, -4) === '.pdb') {
                $symname = substr($symname, 0, -4);
            }
            $symname .= '.sym';

            $futures[$key] = id(new \HTTPSFuture('https://s3-us-west-2.amazonaws.com/org.mozilla.crash-stats.symbols-public/v1/' . urlencode($name) . '/' . $identifier . '/' . urlencode($symname)))
                ->setExpectStatus(array(200, 404));
        }

        $count = count($futures);
        $output->writeln('Downloading ' . $count . ' missing symbols');

        if ($count === 0) {
            return;
        }

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $count);

        $failed = 0;

        // Only run 10 concurrent requests.
        // I'm unsure on what Moz would consider fair here, 1 might be better but is slooooow.
        // FutureIterator returns them in the order they resolve, so running concurrently lets the later stages optimize.
        foreach (id(new \FutureIterator($futures))->limit(10) as $key => $future) {
            list($status, $body, $headers) = $future->resolve();

            if ($status->isError()) {
                throw $status;
            }

            $module = $modules[$key];

            $name = $module['name'];
            $identifier = $module['identifier'];

            if ($status instanceof \HTTPFutureHTTPResponseStatus && $status->getStatusCode() === 404) {
                //$output->writeln('');
                //$output->writeln(json_encode($module));

                if (!isset($blacklist[$name])) {
                    $blacklist[$name] = [
                        '_total' => 1,
                        $identifier => 1,
                    ];
                } else {
                    $blacklist[$name]['_total'] += 1;

                    if (!isset($blacklist[$name][$identifier])) {
                        $blacklist[$name][$identifier] = 1;
                    } else {
                        $blacklist[$name][$identifier] += 1;
                    }
                }

                $failed += 1;

                if (($failed % 50) === 0) {
                    $output->writeln('');
                    $output->writeln('Sending blacklist checkpoint...');

                    $app['redis']->set('throttle:cache:blacklist:mozilla', json_encode($blacklist));
                }

                $progress->advance();
                continue;
            }

            // Reset the total on any successful download.
            if (isset($blacklist[$name])) {
                $blacklist[$name]['_total'] = 0;
            }

            $symdir = \Filesystem::createDirectory($app['root'] . '/symbols/mozilla/' . $name . '/' . $identifier, 0755, true);

            $symname = $name;
            if (substr($symname, -4) === '.pdb') {
                $symname = substr($symname, 0, -4);
            }
            $symname .= '.sym.gz';

            \Filesystem::writeFile($symdir . '/' . $symname, $body);

            // And finally mark the module as having symbols present.
            $app['db']->executeUpdate('UPDATE module SET present = ? WHERE name = ? AND identifier = ?', array(1, $name, $identifier));

            $progress->advance();
        }

        $app['redis']->set('throttle:cache:blacklist:mozilla', json_encode($blacklist));

        $progress->finish();

        $output->writeln($failed . ' symbols failed to download');

        if ($failed === $count) {
            return;
        }

        $output->writeln('Waiting for processing lock...');

        $lock = \PhutilFileLock::newForPath($app['root'] . '/cache/process.lck');
        $lock->lock(300);

        $app['redis']->del('throttle:cache:symbol');

        $output->writeln('Flushed symbol cache');

        $lock->unlock();
    }
}

