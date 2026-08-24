<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * IMPORTANT: The e-mail column has BOTH a Doctrine-level UniqueConstraint
 * (enforced by the application) AND a database-level UNIQUE INDEX created by
 * the migration. NOTE: the database index is the source of truth — it
 * guarantees e-mail uniqueness even when many concurrent writers push data
 * into the table. See {@see Version20260823000001}.
 */
#[UniqueEntity(
    fields: ['email'],
    message: 'An account with this email address already exists.'
)]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Index(name: 'IDX_USER_LAST_LOGIN', columns: ['last_login_at'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Please enter your email address.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[Assert\NotBlank(message: 'Please enter your name.')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Your name must be at least {{ limit }} characters long.',
        maxMessage: 'Your name cannot be longer than {{ limit }} characters.',
    )]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * NOTE: status is stored as a string column but represented in code as
     * the {@see UserStatus} enum. Persistence layer cares about the string.
     */
    #[ORM\Column(length: 20)]
    private UserStatus $status = UserStatus::Unverified;

    /**
     * IMPORTANT: status the user had right BEFORE being blocked. Restored
     * by the "Unblock" toolbar action so an unverified→blocked→unblocked
     * user goes back to Unverified (not Active).
     *
     * NOTE: NULL while the user is not blocked.
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?UserStatus $previousStatus = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * IMPORTANT: opaque token sent in the verification e-mail.
     * Cleared (null) after a successful verification.
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $emailVerificationToken = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus|string $status): static
    {
        $this->status = \is_string($status)
            ? UserStatus::from($status)
            : $status;

        return $this;
    }

    /**
     * IMPORTANT: pre-block status, restored on unblock.
     */
    public function getPreviousStatus(): ?UserStatus
    {
        return $this->previousStatus;
    }

    public function setPreviousStatus(?UserStatus $previousStatus): static
    {
        $this->previousStatus = $previousStatus;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $token): static
    {
        $this->emailVerificationToken = $token;

        return $this;
    }
}