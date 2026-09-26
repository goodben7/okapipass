<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\SellerBonusLedgerRepository;
use App\State\Agency\AgencyScopedItemProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SellerBonusLedgerRepository::class)]
#[ORM\Table(name: '`seller_bonus_ledger`')]
#[ORM\UniqueConstraint(name: 'UNIQ_SELLER_BONUS_PERIOD', fields: ['agency', 'seller', 'periodStart', 'periodEnd', 'rule'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SellerBonusLedger',
    normalizationContext: ['groups' => ['seller_bonus_ledger:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/pos/seller-bonuses',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/pos/seller-bonuses/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'seller' => 'exact',
    'seller.id' => 'exact',
    'rule' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['periodStart', 'periodEnd', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'periodStart', 'amount'])]
class SellerBonusLedger implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SB';

    public const string STATUS_ACCRUED = 'ACCRUED';
    public const string STATUS_PAID = 'PAID';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SB_ID', length: 16)]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SB_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SB_SELLER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?User $seller = null;

    #[ORM\Column(name: 'SB_AMOUNT')]
    #[Groups(['seller_bonus_ledger:get'])]
    private int $amount = 0;

    #[ORM\Column(name: 'SB_CURRENCY', length: 3)]
    #[Groups(['seller_bonus_ledger:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'SB_PERIOD_START', type: Types::DATE_IMMUTABLE)]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(name: 'SB_PERIOD_END', type: Types::DATE_IMMUTABLE)]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SB_RULE', nullable: false, referencedColumnName: 'SC_ID')]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?SellerCommissionRule $rule = null;

    #[ORM\Column(name: 'SB_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['seller_bonus_ledger:get'])]
    private string $status = self::STATUS_ACCRUED;

    #[ORM\Column(name: 'SB_CREATED_AT')]
    #[Groups(['seller_bonus_ledger:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_ACCRUED, self::STATUS_PAID];
    }

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

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function setSeller(?User $seller): static
    {
        $this->seller = $seller;

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

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;

        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;

        return $this;
    }

    public function getRule(): ?SellerCommissionRule
    {
        return $this->rule;
    }

    public function setRule(?SellerCommissionRule $rule): static
    {
        $this->rule = $rule;

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
