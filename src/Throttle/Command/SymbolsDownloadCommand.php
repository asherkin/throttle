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
            ->setDescription('Download missing symbol files from the Microsoft Symbol Server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        if (\Filesystem::resolveBinary('wine') === null || \Filesystem::resolveBinary('cabextract') === null) {
            throw new \RuntimeException('\'wine\' and \'cabextract\' need to be available in your PATH to use this command');
        }

        // Initialize the wine environment.
        execx('WINEPREFIX=%s WINEDEBUG=-all wine regsvr32 %s', $app['root'] . '/.wine', $app['root'] . '/bin/msdia80.dll');

        // Find all Windows modules missing symbols
        $modules = $app['db']->executeQuery('SELECT DISTINCT name, identifier FROM module WHERE name LIKE \'%.pdb\' AND present = 0')->fetchAll();

        // Prepare HTTPSFutures for downloading compressed PDBs.
        $futures = array();
        foreach ($modules as $key => $module) {
            $compressedName = substr($module['name'], 0, -1) . '_';
            $futures[$key] = id(new \HTTPSFuture('http://msdl.microsoft.com/download/symbols/' . $module['name'] . '/' . $module['identifier'] . '/' . $compressedName))
                ->addHeader('User-Agent', 'Microsoft-Symbol-Server')->setFollowLocation(false)->setExpectStatus(array(302, 404));
        }

        $count = count($modules);
        $output->writeln('Found ' . $count . ' missing symbols');

        if ($count === 0) {
            return;
        }

        $cache = \Filesystem::createDirectory($app['root'] . '/cache/pdbs', 0777, true);

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $count);

        // Only run 5 concurrent requests.
        // I'm unsure on what MS would consider fair here, 1 might be better but is slooooow.
        // Futures() returns them in the order they resolve, so running concurrently lets the later stages optimize.
        foreach (\Futures($futures)->limit(5) as $key => $future) {
            list($status, $body, $headers) = $future->resolve();

            if ($status->isError()) {
                throw $status;
            }

            if ($status instanceof \HTTPFutureResponseStatusHTTP && $status->getStatusCode() === 404) {
                $progress->advance();
                continue;
            }

            $module = $modules[$key];
            $prefix = $cache . '/' . $module['name'] . '-' . $module['identifier'];
            $compressedName = substr($module['name'], 0, -1) . '_';

            // Write the compressed PDB.
            \Filesystem::createDirectory($prefix, 0777, true);
            \Filesystem::writeFile($prefix . '/' . $compressedName, $body);

            // Unpack it and removed the compressed copy.
            execx('cabextract -p %s > %s', $prefix . '/' . $compressedName, $prefix . '/' . $module['name']);
            \Filesystem::remove($prefix . '/' . $compressedName);

            // Finally, dump the symbols.
            $symfile = substr($module['name'], 0, -3) . 'sym';
            $symdir = \Filesystem::createDirectory($app['root'] . '/symbols/microsoft/' . $module['name'] . '/' . $module['identifier'], 0755, true);
            
            $failed = false;
            try {
                execx('WINEPREFIX=%s WINEDEBUG=-all wine %s %s > %s',
                    $app['root'] . '/.wine', $app['root'] . '/bin/dump_syms.exe', $prefix . '/' . $module['name'], $symdir . '/' . $symfile);
            } catch (\CommandException $e) {
                $failed = true;
                $output->writeln("\r" . 'Failed to process: ' . $module['name'] . ' ' . $module['identifier']);

                // While a bit messy, we need to delete the orphan symbol file to stop it being marked as present.
                \Filesystem::remove($symdir . '/' . $symfile);
            }

            // Delete the PDB and the working dir.
            \Filesystem::remove($prefix . '/' . $module['name']);
            \Filesystem::remove($prefix);

            // And finally mark the module as having symbols present.
            $app['db']->executeUpdate('UPDATE module SET present = ? WHERE name = ? AND identifier = ?', array(!$failed, $module['name'], $module['identifier']));

            $progress->advance();
        }

        $progress->finish();

        \Filesystem::remove($cache);
    }
}

