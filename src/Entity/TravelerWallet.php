<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\TravelerWalletRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TravelerWalletRepository::class)]
#[ORM\Table(name: '`traveler_wallet`')]
#[ORM\UniqueConstraint(name: 'UNIQ_TRAVELER_WALLET_USER_CURRENCY', fields: ['user', 'currency'])]
#[ORM\HasLifecycleCallbacks]
class TravelerWallet implements RessourceInterface
{
    public const string ID_PREFIX = 'WA';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'WA_ID', length: 16)]
    #[Groups(['traveler_wallet:get', 'wallet_ledger:get', 'wallet_topup:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WA_USER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['traveler_wallet:get'])]
    private ?User $user = null;

    #[ORM\Column(name: 'WA_CURRENCY', length: 3, options: ['default' => Agency::DEFAULT_CURRENCY])]
    #[Groups(['traveler_wallet:get', 'wallet_ledger:get', 'wallet_topup:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'WA_BALANCE', options: ['default' => 0])]
    #[Groups(['traveler_wallet:get'])]
    private int $balance = 0;

    #[ORM\Column(name: 'WA_CREATED_AT')]
    #[Groups(['traveler_wallet:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'WA_UPDATED_AT', nullable: true)]
    #[Groups(['traveler_wallet:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function setBalance(int $balance): static
    {
        $this->balance = $balance;

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
