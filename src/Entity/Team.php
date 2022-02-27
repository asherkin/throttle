<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team extends ServerOwner
{
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'teams')]
    #[ORM\JoinColumn(nullable: false)]
    protected User $owner;

    public function getIcon(): string
    {
        return 'people-fill';
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        if ($owner === null) {
            unset($this->owner);
        } else {
            $this->owner = $owner;
        }

        return $this;
    }
}
