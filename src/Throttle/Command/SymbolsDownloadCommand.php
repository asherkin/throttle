<?php

namespace Throttle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SymbolsDownloadCommand extends Command
{
    protected function configure()
    {
        $this->setName('symbols:download')
            ->setDescription('Download missing symbol files from the Microsoft Symbol Server.')
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

        if (\Filesystem::resolveBinary('wine') === null || \Filesystem::resolveBinary('cabextract') === null) {
            throw new \RuntimeException('\'wine\' and \'cabextract\' need to be available in your PATH to use this command');
        }

        $limit = $input->getOption('limit');

        if ($limit !== null && !ctype_digit($limit)) {
            throw new \InvalidArgumentException('\'limit\' must be an integer');
        }

        // Initialize the wine environment.
        execx('WINEPREFIX=%s WINEDEBUG=-all wine regsvr32 %s', $app['root'] . '/.wine', $app['root'] . '/bin/msdia80.dll');

        $manualName = $input->getArgument('name');
        $manualIdentifier = $input->getArgument('identifier');

        $modules = Array();

        if ($manualName) {
            if (!$manualIdentifier) {
                throw new \RuntimeException('Specifying \'name\' requires specifying \'identifier\' as well.');
            }

            $modules[] = Array('name' => $manualName, 'identifier' => $manualIdentifier);
        } else {
            // Find all Windows modules missing symbols
            $query = 'SELECT DISTINCT name, identifier FROM module WHERE name LIKE \'%.pdb\' AND present = 0';

            if ($limit !== null) {
                $query .= ' LIMIT ' . $limit;
            }

            $modules = $app['db']->executeQuery($query)->fetchAll();
        }

        $blacklist = null;

        $blacklistJson = $app['redis']->get('throttle:cache:blacklist');

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

            $compressedName = substr($name, 0, -1) . '_';
            $futures[$key] = id(new \HTTPSFuture('http://msdl.microsoft.com/download/symbols/' . urlencode($name) . '/' . $identifier . '/' . urlencode($compressedName)))
                ->addHeader('User-Agent', 'Microsoft-Symbol-Server')->setFollowLocation(false)->setExpectStatus(array(200, 302, 404));
        }

        $downloaded = 0;
        $count = count($futures);
        $output->writeln('Downloading ' . $count . ' missing symbols');

        if ($count === 0) {
            return;
        }

        $cache = \Filesystem::createDirectory($app['root'] . '/cache/pdbs', 0777, true);

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $count);

        // Only run 10 concurrent requests.
        // I'm unsure on what MS would consider fair here, 1 might be better but is slooooow.
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

                $progress->advance();
                continue;
            }

            // Reset the total on any successful download.
            if (isset($blacklist[$name])) {
                $blacklist[$name]['_total'] = 0;
            }

            $prefix = $cache . '/' . $name . '-' . $identifier;
            $compressedName = substr($name, 0, -1) . '_';

            // Write the compressed PDB.
            \Filesystem::createDirectory($prefix, 0777, true);
            \Filesystem::writeFile($prefix . '/' . $compressedName, $body);

            // Unpack it and removed the compressed copy.
            execx('cabextract -p %s > %s', $prefix . '/' . $compressedName, $prefix . '/' . $name);
            \Filesystem::remove($prefix . '/' . $compressedName);

            // Finally, dump the symbols.
            $symfile = substr($name, 0, -3) . 'sym.gz';
            $symdir = \Filesystem::createDirectory($app['root'] . '/symbols/microsoft/' . $name . '/' . $identifier, 0755, true);
            
            $failed = false;
            try {
                execx('WINEPREFIX=%s WINEDEBUG=-all wine %s %s | gzip > %s',
                    $app['root'] . '/.wine', $app['root'] . '/bin/dump_syms.exe', $prefix . '/' . $name, $symdir . '/' . $symfile);

                $downloaded += 1;
            } catch (\CommandException $e) {
                $failed = true;
                $output->writeln("\r" . 'Failed to process: ' . $name . ' ' . $identifier);

                // While a bit messy, we need to delete the orphan symbol file to stop it being marked as present.
                \Filesystem::remove($symdir . '/' . $symfile);
            }

            // Delete the PDB and the working dir.
            \Filesystem::remove($prefix . '/' . $name);
            \Filesystem::remove($prefix);

            // And finally mark the module as having symbols present.
            $app['db']->executeUpdate('UPDATE module SET present = ? WHERE name = ? AND identifier = ?', array(!$failed, $name, $identifier));

            $progress->advance();
        }

        $app['redis']->set('throttle:cache:blacklist', json_encode($blacklist));

        $progress->finish();

        \Filesystem::remove($cache);

        if ($downloaded <= 0) {
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

