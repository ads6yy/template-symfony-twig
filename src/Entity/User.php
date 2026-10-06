<?php

declare(strict_types=1);

namespace App\Entity;

use App\Constants\User\AccountStatus;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'string', enumType: AccountStatus::class)]
    private AccountStatus $accountStatus = AccountStatus::ACTIVE;

    #[ORM\Column]
    private bool $isVerified = false;

    /**
     * TOTP secret (base32). Null means two-factor authentication is disabled.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    /**
     * @var list<string>|null SHA-256 hashes of the remaining single-use backup codes
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->accountStatus = AccountStatus::ACTIVE;
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
     * @return non-empty-string
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        if (!is_string($this->email) || $this->email === '') {
            throw new LogicException('User identifier (email) is not set.');
        }

        return $this->email;
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        $first = $this->firstName ?? '';
        $last = $this->lastName ?? '';

        return trim("$first $last");
    }

    public function getAccountStatus(): AccountStatus
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(AccountStatus|string $accountStatus): void
    {
        $this->accountStatus = is_string($accountStatus)
            ? AccountStatus::from($accountStatus)
            : $accountStatus;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->totpSecret !== null;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return (string) $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if ($this->totpSecret === null) {
            return null;
        }

        // SHA1 / 30s / 6 digits: the settings supported by all common authenticator apps.
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    /**
     * @param list<string> $plainCodes
     */
    public function setBackupCodes(array $plainCodes): static
    {
        $this->backupCodes = array_map(self::hashBackupCode(...), $plainCodes);

        return $this;
    }

    public function countBackupCodes(): int
    {
        return count($this->backupCodes ?? []);
    }

    public function disableTwoFactor(): static
    {
        $this->totpSecret = null;
        $this->backupCodes = null;

        return $this;
    }

    public function isBackupCode(string $code): bool
    {
        $hash = self::hashBackupCode($code);
        foreach ($this->backupCodes ?? [] as $storedHash) {
            if (hash_equals($storedHash, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function invalidateBackupCode(string $code): void
    {
        $hash = self::hashBackupCode($code);
        $this->backupCodes = array_values(array_filter(
            $this->backupCodes ?? [],
            static fn (string $storedHash): bool => !hash_equals($storedHash, $hash),
        ));
    }

    /**
     * Backup codes are high-entropy random strings, so a fast hash is enough
     * to avoid storing them in clear text.
     */
    private static function hashBackupCode(string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        if ($this->password !== null) {
            $data["\0".self::class."\0password"] = hash('crc32c', $this->password);
        }
        // Two-factor secrets never need to live in the session: the user is refreshed from the database.
        $data["\0".self::class."\0totpSecret"] = null;
        $data["\0".self::class."\0backupCodes"] = null;

        return $data;
    }
}
