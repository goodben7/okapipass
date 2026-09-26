<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\WalletTopupRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WalletTopupRepository::class)]
#[ORM\Table(name: '`wallet_topup`')]
#[ORM\Index(name: 'IDX_WALLET_TOPUP_STATUS', columns: ['WT_STATUS'])]
#[ORM\Index(name: 'IDX_WALLET_TOPUP_PROVIDER_TX', columns: ['WT_PROVIDER_TX'])]
#[ORM\HasLifecycleCallbacks]
class WalletTopup implements RessourceInterface
{
    public const string ID_PREFIX = 'WT';

    public const string STATUS_PENDING = 'PENDING';
    public const string STATUS_PAID = 'PAID';
    public const string STATUS_FAILED = 'FAILED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    public const string METHOD_MOBILE_MONEY = 'MOBILE_MONEY';
    public const string METHOD_CARD = 'CARD';

    public const string PROVIDER_FLEXPAY = 'FLEXPAY';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'WT_ID', length: 16)]
    #[Groups(['wallet_topup:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WT_WALLET', nullable: false, referencedColumnName: 'WA_ID')]
    #[Groups(['wallet_topup:get'])]
    private ?TravelerWallet $wallet = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WT_USER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['wallet_topup:get'])]
    private ?User $user = null;

    #[ORM\Column(name: 'WT_AMOUNT')]
    #[Groups(['wallet_topup:get'])]
    private int $amount = 0;

    #[ORM\Column(name: 'WT_CURRENCY', length: 3)]
    #[Groups(['wallet_topup:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'WT_STATUS', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['wallet_topup:get'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'WT_METHOD', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getMethodsAsList'])]
    #[Groups(['wallet_topup:get'])]
    private string $method = self::METHOD_MOBILE_MONEY;

    #[ORM\Column(name: 'WT_PHONE', length: 20, nullable: true)]
    #[Groups(['wallet_topup:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'WT_PROVIDER', length: 20, nullable: true)]
    #[Groups(['wallet_topup:get'])]
    private ?string $provider = null;

    #[ORM\Column(name: 'WT_PROVIDER_TX', length: 80, nullable: true)]
    #[Groups(['wallet_topup:get'])]
    private ?string $providerTx = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'WT_PROVIDER_RESPONSE', type: Types::JSON, nullable: true)]
    #[Groups(['wallet_topup:get'])]
    private ?array $providerResponse = null;

    #[ORM\Column(name: 'WT_PAID_AT', nullable: true)]
    #[Groups(['wallet_topup:get'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'WT_CREATED_AT')]
    #[Groups(['wallet_topup:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'WT_UPDATED_AT', nullable: true)]
    #[Groups(['wallet_topup:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    /** @return list<string> */
    public static function getMethodsAsList(): array
    {
        return [self::METHOD_MOBILE_MONEY, self::METHOD_CARD];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getWallet(): ?TravelerWallet
    {
        return $this->wallet;
    }

    public function setWallet(?TravelerWallet $wallet): static
    {
        $this->wallet = $wallet;

        return $this;
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

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderTx(): ?string
    {
        return $this->providerTx;
    }

    public function setProviderTx(?string $providerTx): static
    {
        $this->providerTx = $providerTx;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getProviderResponse(): ?array
    {
        return $this->providerResponse;
    }

    /** @param array<string, mixed>|null $providerResponse */
    public function setProviderResponse(?array $providerResponse): static
    {
        $this->providerResponse = $providerResponse;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

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
