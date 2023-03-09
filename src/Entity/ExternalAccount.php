<?php

namespace App\Entity;

use App\Repository\ExternalAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExternalAccountRepository::class)]
#[ORM\UniqueConstraint(fields: ['kind', 'identifier'])]
class ExternalAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected int $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'externalAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    protected User $user;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $kind;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $identifier;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $displayName;

    public function __construct(User $user, string $kind, string $identifier, string $displayName)
    {
        $this->user = $user;
        $this->kind = $kind;
        $this->identifier = $identifier;
        $this->displayName = $displayName;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        if ($user === null) {
            unset($this->user);
        } else {
            $this->user = $user;
        }

        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }
}
