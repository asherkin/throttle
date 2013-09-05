<?php

namespace Throttle;

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

        $users = $app['db']->executeQuery('SELECT id FROM user WHERE updated IS NULL OR updated < DATE_SUB(NOW(), INTERVAL 1 DAY)');

        $futures = array();
        while (($user = $users->fetchColumn(0)) !== false) {
            $futures[$user] = new \HTTPSFuture('http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $app['config']['apikey'] . '&steamids=' . $user);
        }

        $output->writeln('Found ' . count($futures) . ' stale user(s)');

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, count($futures));

        foreach (\Futures($futures)->limit(5) as $user => $future) {
            list($status, $body, $headers) = $future->resolve();

            if ($status->isError()) {
                continue;
            }

            $data = json_decode($body);

            if ($data === null || empty($data->response->players)) {
                continue;
            }

            $data = $data->response->players[0];

            $app['db']->executeUpdate('UPDATE user SET name = ?, avatar = ?, updated = NOW() WHERE id = ?', array($data->personaname, $data->avatarfull, $user));

            $progress->advance();
        }

        $progress->finish();
    }
}

