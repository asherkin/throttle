<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int $id;

    #[ORM\ManyToOne(targetEntity: ServerOwner::class, inversedBy: 'servers')]
    #[ORM\JoinColumn(nullable: false)]
    protected ServerOwner $owner;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $name = '';

    public function getId(): int
    {
        return $this->id;
    }

    public function getOwner(): ServerOwner
    {
        return $this->owner;
    }

    public function setOwner(?ServerOwner $owner): self
    {
        if ($owner === null) {
            unset($this->owner);
        } else {
            $this->owner = $owner;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
}
