<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Repository\IssuedOkapiPassRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Issued ONT Pass instances (OP-…) for validation at agency desk.
 */
#[ORM\Entity(repositoryClass: IssuedOkapiPassRepository::class)]
#[ORM\Table(name: '`issued_okapi_pass`')]
#[ORM\UniqueConstraint(name: 'UNIQ_ISSUED_OKAPI_PASS_REF', fields: ['reference'])]
#[ORM\HasLifecycleCallbacks]
class IssuedOkapiPass
{
    public const string ID_PREFIX = 'IP';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_EXPIRED = 'EXPIRED';
    public const string STATUS_REVOKED = 'REVOKED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'IP_ID', length: 16)]
    private ?string $id = null;

    #[ORM\Column(name: 'IP_REFERENCE', length: 40)]
    #[Assert\NotBlank]
    private ?string $reference = null;

    #[ORM\Column(name: 'IP_HOLDER_NAME', length: 120)]
    private ?string $holderName = null;

    #[ORM\Column(name: 'IP_STATUS', length: 12)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'IP_EXPIRES_AT', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'IP_CREATED_AT')]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = strtoupper(trim($reference));

        return $this;
    }

    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    public function setHolderName(string $holderName): static
    {
        $this->holderName = $holderName;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isCurrentlyValid(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable('now');
        if (self::STATUS_ACTIVE !== $this->status) {
            return false;
        }
        if (null !== $this->expiresAt && $this->expiresAt < $at) {
            return false;
        }

        return true;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
    }
}
