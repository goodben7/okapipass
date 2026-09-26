<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\WalletLedgerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WalletLedgerRepository::class)]
#[ORM\Table(name: '`wallet_ledger`')]
#[ORM\Index(name: 'IDX_WALLET_LEDGER_WALLET_CREATED', columns: ['WL_WALLET', 'WL_CREATED_AT'])]
#[ORM\HasLifecycleCallbacks]
class WalletLedger implements RessourceInterface
{
    public const string ID_PREFIX = 'WL';

    public const string TYPE_TOPUP = 'TOPUP';
    public const string TYPE_DEBIT = 'DEBIT';
    public const string TYPE_ADJUST = 'ADJUST';
    public const string TYPE_REFUND = 'REFUND';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'WL_ID', length: 16)]
    #[Groups(['wallet_ledger:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WL_WALLET', nullable: false, referencedColumnName: 'WA_ID')]
    #[Groups(['wallet_ledger:get'])]
    private ?TravelerWallet $wallet = null;

    #[ORM\Column(name: 'WL_TYPE', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getTypesAsList'])]
    #[Groups(['wallet_ledger:get'])]
    private string $type = self::TYPE_TOPUP;

    #[ORM\Column(name: 'WL_AMOUNT')]
    #[Groups(['wallet_ledger:get'])]
    private int $amount = 0;

    #[ORM\Column(name: 'WL_BALANCE_AFTER')]
    #[Groups(['wallet_ledger:get'])]
    private int $balanceAfter = 0;

    #[ORM\Column(name: 'WL_CURRENCY', length: 3)]
    #[Groups(['wallet_ledger:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'WL_REFERENCE', length: 80, nullable: true)]
    #[Groups(['wallet_ledger:get'])]
    private ?string $reference = null;

    #[ORM\Column(name: 'WL_LABEL', length: 160, nullable: true)]
    #[Groups(['wallet_ledger:get'])]
    private ?string $label = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'WL_META', type: Types::JSON, nullable: true)]
    #[Groups(['wallet_ledger:get'])]
    private ?array $meta = null;

    #[ORM\Column(name: 'WL_CREATED_AT')]
    #[Groups(['wallet_ledger:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getTypesAsList(): array
    {
        return [
            self::TYPE_TOPUP,
            self::TYPE_DEBIT,
            self::TYPE_ADJUST,
            self::TYPE_REFUND,
        ];
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;

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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /** @param array<string, mixed>|null $meta */
    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

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
