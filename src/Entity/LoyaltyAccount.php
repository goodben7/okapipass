<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\LoyaltyAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LoyaltyAccountRepository::class)]
#[ORM\Table(name: '`loyalty_account`')]
#[ORM\UniqueConstraint(name: 'UNIQ_LOYALTY_ACCOUNT_AGENCY_PHONE', fields: ['agency', 'phone'])]
#[ORM\HasLifecycleCallbacks]
class LoyaltyAccount implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'LA';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'LA_ID', length: 16)]
    #[Groups(['loyalty_account:get', 'loyalty_point_ledger:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'LA_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['loyalty_account:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'LA_PHONE', length: 20)]
    #[Groups(['loyalty_account:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'LA_POINTS', options: ['default' => 0])]
    #[Groups(['loyalty_account:get'])]
    private int $points = 0;

    #[ORM\Column(name: 'LA_CREATED_AT')]
    #[Groups(['loyalty_account:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'LA_UPDATED_AT', nullable: true)]
    #[Groups(['loyalty_account:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(?Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
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

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
