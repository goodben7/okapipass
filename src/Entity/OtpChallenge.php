<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\OtpChallengeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: OtpChallengeRepository::class)]
#[ORM\Table(name: '`otp_challenge`')]
#[ORM\HasLifecycleCallbacks]
class OtpChallenge implements RessourceInterface
{
    public const string ID_PREFIX = 'OC';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'OC_ID', length: 16)]
    #[Groups(['otp_challenge:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'OC_PHONE', length: 20)]
    #[Groups(['otp_challenge:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'OC_CODE_HASH', length: 64)]
    private ?string $codeHash = null;

    #[ORM\Column(name: 'OC_PURPOSE', length: 40)]
    #[Groups(['otp_challenge:get'])]
    private ?string $purpose = null;

    #[ORM\Column(name: 'OC_EXPIRES_AT')]
    #[Groups(['otp_challenge:get'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'OC_CONSUMED_AT', nullable: true)]
    #[Groups(['otp_challenge:get'])]
    private ?\DateTimeImmutable $consumedAt = null;

    #[ORM\Column(name: 'OC_ATTEMPTS')]
    #[Groups(['otp_challenge:get'])]
    private int $attempts = 0;

    #[ORM\Column(name: 'OC_CREATED_AT')]
    #[Groups(['otp_challenge:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCodeHash(): ?string
    {
        return $this->codeHash;
    }

    public function setCodeHash(string $codeHash): static
    {
        $this->codeHash = $codeHash;

        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeImmutable $consumedAt): static
    {
        $this->consumedAt = $consumedAt;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
    }
}
