<?php

namespace App\Twig;

use Symfony\Bridge\Twig\AppVariable as SymfonyAppVariable;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AppVariable
{
    private $cache;
    private $rootPath;
    private $appConfig;
    private $inner;

    public function __construct(CacheInterface $cache, $rootPath, $appConfig, SymfonyAppVariable $inner)
    {
        $this->cache = $cache;
        $this->rootPath = $rootPath;
        $this->appConfig = $appConfig;
        $this->inner = $inner;
    }

    public function getToken()
    {
        return $this->inner->getToken();
    }

    public function getUser()
    {
        return $this->inner->getUser();
    }

    public function getRequest()
    {
        return $this->inner->getRequest();
    }

    public function getSession()
    {
        return $this->inner->getSession();
    }

    public function getEnvironment()
    {
        return $this->inner->getEnvironment();
    }

    public function getDebug()
    {
        return $this->inner->getDebug();
    }

    public function getFlashes($types = null)
    {
        return $this->inner->getFlashes($types);
    }

    public function getConfig()
    {
        return $this->appConfig;
    }

    public function getFeature()
    {
        return [
            'subscriptions' => false,
        ];
    }

    public function getVersion()
    {
        return $this->cache->get('git_version', function (ItemInterface $item) {
            $item->expiresAfter(60);

            $process = (new Process(['/usr/bin/git', 'describe', '--abbrev=12', '--always', '--dirty=+']))
                ->setWorkingDirectory($this->rootPath)
                ->setTimeout(1);

            $ret = $process->run();

            if ($ret !== 0) {
                return null;
            }

            return $process->getOutput();
        });
    }
}
