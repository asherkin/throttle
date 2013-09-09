<?php

namespace Throttle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SymbolsDumpCommand extends Command
{
    protected function configure()
    {
        $this->setName('symbols:dump')
            ->setDescription('Dump symbol data from binary. This should only be used for binaries missing debugging information.')
            ->addArgument(
                'binary',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Binaries to process, seperate multiple values with a space'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $table = $this->getHelperSet()->get('table');
        $table->setCellHeaderFormat('%s');
        $table->setCellRowFormat('%s');
        $table->setHeaders(array('Binary', 'Identifier'));

        $moduleFutures = array();
        $symbolFutures = array();
        $binaries = $input->getArgument('binary');
        foreach ($binaries as $binary) {
            $moduleFutures[$binary] = new \ExecFuture($app['root'] . '/bin/breakpad_moduleid %s', $binary);
            $symbolFutures[$binary] = new \ExecFuture($app['root'] . '/bin/nm -nC %s', $binary);
        }

        $identifiers = array();
        foreach (\Futures($moduleFutures)->limit(5) as $name => $future) {
            list($stdout, $stderr) = $future->resolvex();
            $identifier = rtrim($stdout);

            $identifiers[$name] = $identifier;
            $table->addRow(array(basename($name), $identifier));
        }

        foreach (\Futures($symbolFutures)->limit(5) as $name => $future) {
            $basename = basename($name);
            $identifier = $identifiers[$name];

            $path = $app['root'] . '/symbols/public/' . $basename . '/' . $identifier;
            $file = $path . '/' . $basename . '.sym';
            \Filesystem::createDirectory($path, 0777, true);

            \Filesystem::writeFile($file, 'MODULE Linux x86 ' . $identifier . ' ' . $basename . PHP_EOL);
            foreach (new \LinesOfALargeExecFuture($future) as $line) {
                if (!preg_match('/^0+([0-9a-fA-F]+) +[tT] +([0-9a-zA-Z_.* ,():&]+)$/', $line, $matches)) {
                    continue;
                }

                \Filesystem::appendFile($file, 'PUBLIC ' . $matches[1] . ' 0 ' . $matches[2] . PHP_EOL);
            }
        }

        $table->render($output);
    }
}

