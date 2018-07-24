<?php

namespace Throttle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserUpdateCommand extends Command
{
    protected function configure()
    {
        $this->setName('user:update')
            ->setDescription('Update user information from Steam.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication()->getContainer();

        if (!$app['config']['apikey']) {
            throw new \Exception('Steam Community API Key not configured');
        }

        $users = $app['db']->executeQuery('SELECT id FROM user WHERE updated IS NULL OR updated < DATE_SUB(NOW(), INTERVAL 1 DAY)');

        $futures = array();
        while (($user = $users->fetchColumn(0)) !== false) {
            $futures[$user] = new \HTTPSFuture('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $app['config']['apikey'] . '&steamids=' . $user);
        }

        $count = count($futures);

        $output->writeln('Found ' . $count . ' stale user(s)');

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, $count);

        $statsUpdated = 0;
        $statsFailed = 0;

        foreach (id(new \FutureIterator($futures))->limit(5) as $user => $future) {
            list($status, $body, $headers) = $future->resolve();

            $progress->advance();

            if ($status->isError()) {
                $statsFailed++;

                continue;
            }

            $data = json_decode($body);

            if ($data === null || empty($data->response->players)) {
                $app['db']->executeUpdate('UPDATE user SET updated = NOW() WHERE id = ?', array($user));

                $statsFailed++;

                continue;
            }

            $data = $data->response->players[0];

            if (!isset($data->avatarfull) || !isset($data->personaname)) {
                $app['db']->executeUpdate('UPDATE user SET updated = NOW() WHERE id = ?', array($user));

                $statsFailed++;

                continue;
            }

            // Valve don't know how to HTTPS.
            $data->avatarfull = str_replace('http://cdn.akamai.steamstatic.com/', 'https://steamcdn-a.akamaihd.net/', $data->avatarfull);

            $app['db']->executeUpdate('UPDATE user SET name = ?, avatar = ?, updated = NOW() WHERE id = ?', array($data->personaname, $data->avatarfull, $user));

            $statsUpdated++;
        }

        $progress->finish();

        $app['redis']->hIncrBy('throttle:stats', 'users:updated', $statsUpdated);
        $app['redis']->hIncrBy('throttle:stats', 'users:failed', $statsFailed);
    }
}

