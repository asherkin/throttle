<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends ServerOwner implements UserInterface
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_ALLOWED_TO_SWITCH = 'ROLE_ALLOWED_TO_SWITCH';

    public const MANAGED_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_ALLOWED_TO_SWITCH,
    ];

    /** @var array<int, string> */
    #[ORM\Column(type: 'json')]
    protected array $roles = [];

    #[ORM\Column(nullable: true)]
    protected ?\DateTimeImmutable $lastLogin = null;

    /** @var Collection<int, ExternalAccount> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ExternalAccount::class, cascade: ['remove'])]
    #[ORM\OrderBy(['kind' => 'ASC', 'displayName' => 'ASC'])]
    protected Collection $externalAccounts;

    /** @var Collection<int, Team> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Team::class, cascade: ['remove'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $teams;

    #[ORM\OneToOne()]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?ExternalAccount $contactEmail = null;

    public function __construct()
    {
        parent::__construct();

        $this->externalAccounts = new ArrayCollection();
        $this->teams = new ArrayCollection();
    }

    public function getIcon(): string
    {
        return 'person-fill';
    }

    /**
     * @see UserInterface
     *
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $roles = array_intersect(self::MANAGED_ROLES, $this->roles);

        // Guarantee every user at least has ROLE_USER
        array_unshift($roles, self::ROLE_USER);

        return array_unique($roles);
    }

    /**
     * @param array<int, string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_intersect(self::MANAGED_ROLES, $this->roles);

        return $this;
    }

    public function getLastLogin(): ?\DateTimeImmutable
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeImmutable $lastLogin): self
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    /**
     * @return Collection<int, ExternalAccount>
     */
    public function getExternalAccounts(): Collection
    {
        return $this->externalAccounts;
    }

    public function addExternalAccount(ExternalAccount $externalAccount): self
    {
        if (!$this->externalAccounts->contains($externalAccount)) {
            $this->externalAccounts[] = $externalAccount;
            $externalAccount->setUser($this);
        }

        return $this;
    }

    public function removeExternalAccount(ExternalAccount $externalAccount): self
    {
        if ($this->externalAccounts->removeElement($externalAccount)) {
            // set the owning side to null (unless already changed)
            if ($externalAccount->getUser() === $this) {
                $externalAccount->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getEmailAddresses(): array
    {
        $externalAccountCriteria = Criteria::create()
            ->where(Criteria::expr()->eq('kind', 'email'));

        return $this->externalAccounts
            ->matching($externalAccountCriteria)
            ->map(fn ($externalAccount) => $externalAccount->getIdentifier())
            ->toArray();
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail?->getIdentifier();
    }

    public function setContactEmail(?string $contactEmail): self
    {
        if ($contactEmail === null) {
            $this->contactEmail = null;

            return $this;
        }

        $externalAccountCriteria = Criteria::create()
            ->where(Criteria::expr()->eq('kind', 'email'))
            ->andWhere(Criteria::expr()->eq('identifier', $contactEmail));

        $externalAccount = $this->externalAccounts
            ->matching($externalAccountCriteria)
            ->first();

        if ($externalAccount === false) {
            throw new \InvalidArgumentException('Contact email does not belong to the user');
        }

        $this->contactEmail = $externalAccount;

        return $this;
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): self
    {
        if (!$this->teams->contains($team)) {
            $this->teams[] = $team;
            $team->setOwner($this);
        }

        return $this;
    }

    public function removeTeam(Team $team): self
    {
        if ($this->teams->removeElement($team)) {
            // set the owning side to null (unless already changed)
            if ($team->getOwner() === $this) {
                $team->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ServerOwner>
     */
    public function getServerOwners(): Collection
    {
        /** @var array<int, ServerOwner> $serverOwners */
        $serverOwners = [$this, ...$this->getTeams()];

        return new ArrayCollection($serverOwners);
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->getId();
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    /**
     * This method can be removed in Symfony 6.0 - is not needed for apps that do not check user passwords.
     *
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return null;
    }

    /**
     * This method can be removed in Symfony 6.0 - is not needed for apps that do not check user passwords.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
