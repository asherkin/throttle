<?php

namespace Throttle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrashCleanCommand extends Command
{
    protected function configure()
    {
        $this->setName('crash:clean')
            ->setDescription('Cleanup old crash dumps.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only list orphan dumps, do not delete them.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        $total_count = 0;

        $groups = $app['db']->executeQuery('SELECT owner, ip, INET6_NTOA(ip) AS display_ip FROM crash GROUP BY owner, ip HAVING (owner IS NULL AND COUNT(*) > 50) OR (owner IS NOT NULL AND COUNT(*) > 100)');

        while ($group = $groups->fetch()) {
            if ($group['owner'] !== null) {
                $query = $app['db']->executeQuery('SELECT id FROM crash WHERE owner = ? AND ip = ? ORDER BY timestamp DESC LIMIT 100 OFFSET 100', array($group['owner'], $group['ip']));
            } else {
                $query = $app['db']->executeQuery('SELECT id FROM crash WHERE owner IS NULL AND ip = ? ORDER BY timestamp DESC LIMIT 100 OFFSET 50', array($group['ip']));
            }

            $crashes = array();
            while ($id = $query->fetchColumn(0)) {
                $crashes[] = $id;
            }

            $count = count($crashes);
            if (!$input->getOption('dry-run') && $count > 0) {
                $count = $app['db']->executeUpdate('DELETE FROM crash WHERE id IN (?) LIMIT 100', array($crashes), array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));

                if ($count > 0) {
                    $app['redis']->hIncrBy('throttle:stats', 'crashes:cleaned:limit', $count);
                }
            }

            $output->writeln('Deleted ' . $count . ' crash dumps for server: ' . ($group['owner'] ?: 'NULL') . ', ' . $group['display_ip']);
            $total_count += $count;
        }

        $count = 0;
        if ($input->getOption('dry-run')) {
            $count = $app['db']->executeQuery('SELECT LEAST(COUNT(*), 100) FROM crash WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)')->fetchColumn(0);
        } else {
            $count = $app['db']->executeUpdate('DELETE FROM crash WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 100');

            if ($count > 0) {
                $app['redis']->hIncrBy('throttle:stats', 'crashes:cleaned:old', $count);
            }
        }

        $output->writeln('Deleted ' . $count . ' old crash dumps.');
        $total_count += $count;

        $output->writeln('Removed ' . $total_count . ' crash dumps from database.');

        $query = $app['db']->executeQuery('SELECT id FROM crash');

        $crashes = array();
        while ($id = $query->fetchColumn(0)) {
            $crashes[$id] = true;
        }

        $output->writeln('Got ' . count($crashes) . ' crashes in database.');

        $count = 0;
        $found = array();
        $buckets = \Filesystem::listDirectory($app['root'] . '/dumps', false);
        foreach ($buckets as $bucket) {
            $dumps = \Filesystem::listDirectory($app['root'] . '/dumps/' . $bucket, false);
            foreach ($dumps as $dump) {
                $filename = explode('.', $dump, 2);
                
                if (isset($crashes[$filename[0]])) {
                    if ($filename[1] === 'dmp') {
                        $found[$filename[0]] = true;
                    }

                    continue;
                }

                if (!$input->getOption('dry-run')) {
                    $path = $app['root'] . '/dumps/' . $bucket . '/' . $dump;
                    \Filesystem::remove($path);
                }

                if ($filename[1] === 'dmp') {
                    $count++;
                }
            }
        }

        $output->writeln('Matched ' . count($found) . ' minidumps to database.');

        if ($count > 0 && !$input->getOption('dry-run')) {
            $app['redis']->hIncrBy('throttle:stats', 'crashes:cleaned:orphan', $count);
        }

        $output->writeln('Deleted ' . $count . ' orphaned minidumps.');

        $missing = array_diff_key($crashes, $found);

        $query = $app['db']->executeQuery('SELECT id FROM crash WHERE id IN (?) AND failed = 1', array(array_keys($missing)), array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

        $missing_errored = array();
        while ($id = $query->fetchColumn(0)) {
            $missing_errored[$id] = true;
        }

        $count = count($missing_errored);
        if (!$input->getOption('dry-run')) {
            $count = $app['db']->executeUpdate('DELETE FROM crash WHERE id IN (?) AND failed = 1 LIMIT 100', array(array_keys($missing)), array(\Doctrine\DBAL\Connection::PARAM_STR_ARRAY));

            if ($count > 0) {
                $app['redis']->hIncrBy('throttle:stats', 'crashes:cleaned:missing', $count);
            }
        }

        $output->writeln('Deleted ' . $count . ' failed crash reports missing minidumps.');

        $anomalous = array_diff_key($missing, $missing_errored);

        if (count($anomalous) > 0) {
            $output->writeln('Found ' . count($anomalous) . ' crash reports missing minidumps that need investigating:');

            print_r($anomalous);
        }
    }
}

