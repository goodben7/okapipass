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
use App\Dto\Agency\CreateLoyaltyRuleDto;
use App\Dto\Agency\UpdateLoyaltyRuleDto;
use App\Model\RessourceInterface;
use App\Repository\LoyaltyRuleRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateLoyaltyRuleProcessor;
use App\State\Agency\UpdateLoyaltyRuleProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoyaltyRuleRepository::class)]
#[ORM\Table(name: '`loyalty_rule`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'LoyaltyRule',
    normalizationContext: ['groups' => ['loyalty_rule:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/loyalty/rules',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/loyalty/rules/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/loyalty/rules',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateLoyaltyRuleDto::class,
            processor: CreateLoyaltyRuleProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/loyalty/rules/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateLoyaltyRuleDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateLoyaltyRuleProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'triggerType' => 'exact',
    'rewardType' => 'exact',
    'active' => 'exact',
    'offer' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'label', 'threshold'])]
class LoyaltyRule implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'LR';

    public const string TRIGGER_TRIP_COUNT = 'trip_count';
    public const string TRIGGER_SPEND = 'spend';
    public const string TRIGGER_FIRST_PURCHASE = 'first_purchase';

    public const string WINDOW_ROLLING_30D = 'rolling_30d';
    public const string WINDOW_CALENDAR_MONTH = 'calendar_month';
    public const string WINDOW_LIFETIME = 'lifetime';

    public const string REWARD_PERCENT_OFF = 'percent_off';
    public const string REWARD_FIXED_OFF = 'fixed_off';
    public const string REWARD_SURPRISE_POOL = 'surprise_pool';
    public const string REWARD_POINTS = 'points';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'LR_ID', length: 16)]
    #[Groups(['loyalty_rule:get', 'agency_ticket:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'LR_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['loyalty_rule:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'LR_LABEL', length: 160)]
    #[Groups(['loyalty_rule:get', 'agency_ticket:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'LR_ORIGIN', length: 120, nullable: true)]
    #[Groups(['loyalty_rule:get'])]
    private ?string $origin = null;

    #[ORM\Column(name: 'LR_DESTINATION', length: 120, nullable: true)]
    #[Groups(['loyalty_rule:get'])]
    private ?string $destination = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'LR_OFFER', nullable: true, referencedColumnName: 'AO_ID')]
    #[Groups(['loyalty_rule:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'LR_TRIGGER_TYPE', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getTriggerTypesAsList'])]
    #[Groups(['loyalty_rule:get'])]
    private string $triggerType = self::TRIGGER_TRIP_COUNT;

    #[ORM\Column(name: 'LR_WINDOW', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getWindowsAsList'])]
    #[Groups(['loyalty_rule:get'])]
    private string $window = self::WINDOW_ROLLING_30D;

    #[ORM\Column(name: 'LR_THRESHOLD')]
    #[Groups(['loyalty_rule:get'])]
    private int $threshold = 0;

    #[ORM\Column(name: 'LR_REWARD_TYPE', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getRewardTypesAsList'])]
    #[Groups(['loyalty_rule:get'])]
    private string $rewardType = self::REWARD_PERCENT_OFF;

    #[ORM\Column(name: 'LR_REWARD_VALUE')]
    #[Groups(['loyalty_rule:get'])]
    private int $rewardValue = 0;

    #[ORM\Column(name: 'LR_MAX_DISCOUNT_AMOUNT', nullable: true)]
    #[Groups(['loyalty_rule:get'])]
    private ?int $maxDiscountAmount = null;

    /** @var list<int>|null 0=Sun … 6=Sat */
    #[ORM\Column(name: 'LR_EXCLUDED_WEEKDAYS', type: Types::JSON, nullable: true)]
    #[Groups(['loyalty_rule:get'])]
    private ?array $excludedWeekdays = null;

    /** @var list<string>|null */
    #[ORM\Column(name: 'LR_EXCLUDED_SEAT_CLASSES', type: Types::JSON, nullable: true)]
    #[Groups(['loyalty_rule:get'])]
    private ?array $excludedSeatClasses = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'LR_SURPRISE_POOL', nullable: true, referencedColumnName: 'SP_ID')]
    #[Groups(['loyalty_rule:get'])]
    private ?SurprisePool $surprisePool = null;

    #[ORM\Column(name: 'LR_POINTS_EARN', options: ['default' => 0])]
    #[Groups(['loyalty_rule:get'])]
    private int $pointsEarn = 0;

    #[ORM\Column(name: 'LR_STACKABLE')]
    #[Groups(['loyalty_rule:get'])]
    private bool $stackable = false;

    #[ORM\Column(name: 'LR_ACTIVE')]
    #[Groups(['loyalty_rule:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'LR_CREATED_AT')]
    #[Groups(['loyalty_rule:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getTriggerTypesAsList(): array
    {
        return [
            self::TRIGGER_TRIP_COUNT,
            self::TRIGGER_SPEND,
            self::TRIGGER_FIRST_PURCHASE,
        ];
    }

    /** @return list<string> */
    public static function getWindowsAsList(): array
    {
        return [
            self::WINDOW_ROLLING_30D,
            self::WINDOW_CALENDAR_MONTH,
            self::WINDOW_LIFETIME,
        ];
    }

    /** @return list<string> */
    public static function getRewardTypesAsList(): array
    {
        return [
            self::REWARD_PERCENT_OFF,
            self::REWARD_FIXED_OFF,
            self::REWARD_SURPRISE_POOL,
            self::REWARD_POINTS,
        ];
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getOffer(): ?AgencyOffer
    {
        return $this->offer;
    }

    public function setOffer(?AgencyOffer $offer): static
    {
        $this->offer = $offer;

        return $this;
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function setTriggerType(string $triggerType): static
    {
        $this->triggerType = $triggerType;

        return $this;
    }

    public function getWindow(): string
    {
        return $this->window;
    }

    public function setWindow(string $window): static
    {
        $this->window = $window;

        return $this;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function setThreshold(int $threshold): static
    {
        $this->threshold = $threshold;

        return $this;
    }

    public function getRewardType(): string
    {
        return $this->rewardType;
    }

    public function setRewardType(string $rewardType): static
    {
        $this->rewardType = $rewardType;

        return $this;
    }

    public function getRewardValue(): int
    {
        return $this->rewardValue;
    }

    public function setRewardValue(int $rewardValue): static
    {
        $this->rewardValue = $rewardValue;

        return $this;
    }

    public function getMaxDiscountAmount(): ?int
    {
        return $this->maxDiscountAmount;
    }

    public function setMaxDiscountAmount(?int $maxDiscountAmount): static
    {
        $this->maxDiscountAmount = $maxDiscountAmount;

        return $this;
    }

    /** @return list<int>|null */
    public function getExcludedWeekdays(): ?array
    {
        return $this->excludedWeekdays;
    }

    /** @param list<int>|null $excludedWeekdays */
    public function setExcludedWeekdays(?array $excludedWeekdays): static
    {
        if (null === $excludedWeekdays) {
            $this->excludedWeekdays = null;

            return $this;
        }

        $normalized = [];
        foreach ($excludedWeekdays as $day) {
            $day = (int) $day;
            if ($day >= 0 && $day <= 6) {
                $normalized[$day] = $day;
            }
        }
        $this->excludedWeekdays = array_values($normalized);

        return $this;
    }

    /** @return list<string>|null */
    public function getExcludedSeatClasses(): ?array
    {
        return $this->excludedSeatClasses;
    }

    /** @param list<string>|null $excludedSeatClasses */
    public function setExcludedSeatClasses(?array $excludedSeatClasses): static
    {
        if (null === $excludedSeatClasses) {
            $this->excludedSeatClasses = null;

            return $this;
        }

        $normalized = [];
        foreach ($excludedSeatClasses as $class) {
            $class = strtoupper(trim((string) $class));
            if ('' !== $class) {
                $normalized[$class] = $class;
            }
        }
        $this->excludedSeatClasses = array_values($normalized);

        return $this;
    }

    public function getSurprisePool(): ?SurprisePool
    {
        return $this->surprisePool;
    }

    public function setSurprisePool(?SurprisePool $surprisePool): static
    {
        $this->surprisePool = $surprisePool;

        return $this;
    }

    public function getPointsEarn(): int
    {
        return $this->pointsEarn;
    }

    public function setPointsEarn(int $pointsEarn): static
    {
        $this->pointsEarn = $pointsEarn;

        return $this;
    }

    public function isStackable(): bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): static
    {
        $this->stackable = $stackable;

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
