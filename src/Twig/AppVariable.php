<?php

namespace App\Twig;

use Symfony\Bridge\Twig\AppVariable as SymfonyAppVariable;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AppVariable
{
    private $inner;
    private $config;

    public function __construct(SymfonyAppVariable $inner, $config)
    {
        $this->inner = $inner;
        $this->config = $config;
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
        return $this->config;
    }
}
