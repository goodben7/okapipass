<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateSellerCommissionRuleDto;
use App\Dto\Agency\UpdateSellerCommissionRuleDto;
use App\Model\RessourceInterface;
use App\Repository\SellerCommissionRuleRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateSellerCommissionRuleProcessor;
use App\State\Agency\UpdateSellerCommissionRuleProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SellerCommissionRuleRepository::class)]
#[ORM\Table(name: '`seller_commission_rule`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SellerCommissionRule',
    normalizationContext: ['groups' => ['seller_commission_rule:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/pos/commission-rules',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/pos/commission-rules/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/pos/commission-rules',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateSellerCommissionRuleDto::class,
            processor: CreateSellerCommissionRuleProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/pos/commission-rules/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateSellerCommissionRuleDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateSellerCommissionRuleProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'periodType' => 'exact',
    'active' => 'exact',
    'bonusType' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'targetTickets', 'targetRevenue'])]
class SellerCommissionRule implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SC';

    public const string PERIOD_DAY = 'day';
    public const string PERIOD_MONTH = 'month';

    public const string BONUS_PERCENT = 'percent';
    public const string BONUS_FIXED = 'fixed';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SC_ID', length: 16)]
    #[Groups(['seller_commission_rule:get', 'seller_bonus_ledger:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SC_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['seller_commission_rule:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'SC_PERIOD_TYPE', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getPeriodTypesAsList'])]
    #[Groups(['seller_commission_rule:get', 'seller_bonus_ledger:get'])]
    private string $periodType = self::PERIOD_DAY;

    #[ORM\Column(name: 'SC_TARGET_TICKETS')]
    #[Groups(['seller_commission_rule:get'])]
    private int $targetTickets = 0;

    #[ORM\Column(name: 'SC_TARGET_REVENUE')]
    #[Groups(['seller_commission_rule:get'])]
    private int $targetRevenue = 0;

    #[ORM\Column(name: 'SC_BONUS_TYPE', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getBonusTypesAsList'])]
    #[Groups(['seller_commission_rule:get'])]
    private string $bonusType = self::BONUS_FIXED;

    #[ORM\Column(name: 'SC_BONUS_VALUE')]
    #[Groups(['seller_commission_rule:get'])]
    private int $bonusValue = 0;

    #[ORM\Column(name: 'SC_ACTIVE')]
    #[Groups(['seller_commission_rule:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'SC_CREATED_AT')]
    #[Groups(['seller_commission_rule:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getPeriodTypesAsList(): array
    {
        return [self::PERIOD_DAY, self::PERIOD_MONTH];
    }

    /** @return list<string> */
    public static function getBonusTypesAsList(): array
    {
        return [self::BONUS_PERCENT, self::BONUS_FIXED];
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

    public function getPeriodType(): string
    {
        return $this->periodType;
    }

    public function setPeriodType(string $periodType): static
    {
        $this->periodType = $periodType;

        return $this;
    }

    public function getTargetTickets(): int
    {
        return $this->targetTickets;
    }

    public function setTargetTickets(int $targetTickets): static
    {
        $this->targetTickets = $targetTickets;

        return $this;
    }

    public function getTargetRevenue(): int
    {
        return $this->targetRevenue;
    }

    public function setTargetRevenue(int $targetRevenue): static
    {
        $this->targetRevenue = $targetRevenue;

        return $this;
    }

    public function getBonusType(): string
    {
        return $this->bonusType;
    }

    public function setBonusType(string $bonusType): static
    {
        $this->bonusType = $bonusType;

        return $this;
    }

    public function getBonusValue(): int
    {
        return $this->bonusValue;
    }

    public function setBonusValue(int $bonusValue): static
    {
        $this->bonusValue = $bonusValue;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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
