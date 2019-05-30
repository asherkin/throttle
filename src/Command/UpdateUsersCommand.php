<?php

namespace App\Command;

use Doctrine\DBAL\Driver\Connection;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpdateUsersCommand extends Command
{
    use LockableTrait;

    private const STEAM_API_URL = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/';

    private $db;
    private $httpClient;
    private $appConfig;

    protected static $defaultName = 'app:update-users';

    public function __construct(Connection $db, HttpClientInterface $httpClient, $appConfig)
    {
        $this->db = $db;
        $this->httpClient = $httpClient;
        $this->appConfig = $appConfig;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Update user information from Steam.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        if (!$this->appConfig['apikey']) {
            throw new Exception('Steam Community API Key not configured');
        }

        $users = $this->db->executeQuery('SELECT id FROM user WHERE updated IS NULL OR updated < DATE_SUB(NOW(), INTERVAL 1 DAY)');

        $pending = [];
        $requests = [];
        $batch = [];

        while (true) {
            $steamid = $users->fetchColumn(0);

            if ($steamid === false || count($batch) >= 100) {
                if (count($batch) <= 0) {
                    break;
                }

                $requests[] = $this->httpClient->request('GET', self::STEAM_API_URL, [
                    'user_data' => count($requests),
                    'query' => [
                        'key' => $this->appConfig['apikey'],
                        'steamids' => implode(',', $batch),
                        'format' => 'json',
                    ],
                ]);

                if ($steamid === false) {
                    break;
                }

                $batch = [];
            }

            $pending[$steamid] = true;
            $batch[] = $steamid;
        }

        $output->write(sprintf('Updating %d users... ', count($pending)));

        foreach ($this->httpClient->stream($requests) as $request => $chunk) {
            if (!$chunk->isLast()) {
                continue;
            }

            $response = $request->toArray();
            $players = $response['response']['players'] ?? [];

            $count = 0;
            $bindings = [];

            foreach ($players as $player) {
                $steamid = $player['steamid'] ?? null;
                $name = $player['personaname'] ?? null;
                $avatar = $player['avatarfull'] ?? null;

                if ($steamid === null || $name === null || $avatar === null) {
                    continue;
                }

                unset($pending[$steamid]);

                // Persona names are often invalid unicode.
                $name = mb_convert_encoding($name, 'utf-8', 'utf-8');
                $name = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $name);

                // The API currently returns HTTPS URLs, but rewrite just in case.
                $avatar = str_replace('http://cdn.akamai.steamstatic.com/', 'https://steamcdn-a.akamaihd.net/', $avatar);

                ++$count;
                $bindings[] = $steamid;
                $bindings[] = $name;
                $bindings[] = $avatar;
            }

            if ($count <= 0) {
                continue;
            }

            $fragment = implode(', ', array_fill(0, $count, '(?, ?, ?, NOW())'));
            $query = 'INSERT INTO user (id, name, avatar, updated) VALUES '.$fragment.' ON DUPLICATE KEY UPDATE name = VALUES(name), avatar = VALUES(avatar), updated = VALUES(updated)';
            $this->db->executeUpdate($query, $bindings);
        }

        $failed = array_keys($pending);
        $count = count($failed);

        if ($count > 0) {
            $fragment = implode(', ', array_fill(0, $count, '(?, NOW())'));
            $query = 'INSERT INTO user (id, updated) VALUES '.$fragment.' ON DUPLICATE KEY UPDATE updated = VALUES(updated)';
            $this->db->executeUpdate($query, $failed);
        }

        $output->writeln('done');
    }
}
