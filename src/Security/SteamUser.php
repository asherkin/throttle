<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class SteamUser implements UserInterface
{
    private $id;
    private $name = null;
    private $avatar = null;
    private $pending = 0;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getPending(): int
    {
        return $this->pending;
    }

    public function setPending(int $pending): self
    {
        $this->pending = $pending;

        return $this;
    }

    public function isAdmin(): bool
    {
        // TODO: Use a role for admin?
        return false;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = [
            'ROLE_USER',
        ];

        return $roles;
    }

    /**
     * @see UserInterface
     */
    public function getPassword()
    {
        // Not needed for apps that do not check user passwords
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // Not needed for apps that do not check user passwords
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return $this->getId();
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
}
