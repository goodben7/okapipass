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
use App\Dto\Agency\CreatePromotionDto;
use App\Dto\Agency\UpdatePromotionDto;
use App\Model\RessourceInterface;
use App\Repository\PromotionRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreatePromotionProcessor;
use App\State\Agency\UpdatePromotionProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PromotionRepository::class)]
#[ORM\Table(name: '`promotion`')]
#[ORM\UniqueConstraint(name: 'UNIQ_PROMOTION_AGENCY_CODE', fields: ['agency', 'code'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'Promotion',
    normalizationContext: ['groups' => ['promotion:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/promotions',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/promotions/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/promotions',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreatePromotionDto::class,
            processor: CreatePromotionProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/promotions/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdatePromotionDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdatePromotionProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'code' => 'iexact',
    'active' => 'exact',
    'discountType' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'code', 'label', 'validTo'])]
class Promotion implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'PM';

    public const string DISCOUNT_PERCENT_OFF = 'percent_off';
    public const string DISCOUNT_FIXED_OFF = 'fixed_off';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PR_ID', length: 16)]
    #[Groups(['promotion:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PR_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['promotion:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'PR_CODE', length: 40)]
    #[Groups(['promotion:get'])]
    private ?string $code = null;

    #[ORM\Column(name: 'PR_LABEL', length: 160)]
    #[Groups(['promotion:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'PR_DISCOUNT_TYPE', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getDiscountTypesAsList'])]
    #[Groups(['promotion:get'])]
    private string $discountType = self::DISCOUNT_PERCENT_OFF;

    #[ORM\Column(name: 'PR_DISCOUNT_VALUE')]
    #[Groups(['promotion:get'])]
    private int $discountValue = 0;

    #[ORM\Column(name: 'PR_MAX_USES', nullable: true)]
    #[Groups(['promotion:get'])]
    private ?int $maxUses = null;

    #[ORM\Column(name: 'PR_MAX_DISCOUNT_AMOUNT', nullable: true)]
    #[Groups(['promotion:get'])]
    private ?int $maxDiscountAmount = null;

    /** @var list<int>|null 0=Sun … 6=Sat */
    #[ORM\Column(name: 'PR_EXCLUDED_WEEKDAYS', type: Types::JSON, nullable: true)]
    #[Groups(['promotion:get'])]
    private ?array $excludedWeekdays = null;

    /** @var list<string>|null e.g. PREMIUM, STANDARD */
    #[ORM\Column(name: 'PR_EXCLUDED_SEAT_CLASSES', type: Types::JSON, nullable: true)]
    #[Groups(['promotion:get'])]
    private ?array $excludedSeatClasses = null;

    #[ORM\Column(name: 'PR_MAX_USES_PER_PHONE', nullable: true)]
    #[Groups(['promotion:get'])]
    private ?int $maxUsesPerPhone = null;

    #[ORM\Column(name: 'PR_USED_COUNT')]
    #[Groups(['promotion:get'])]
    private int $usedCount = 0;

    #[ORM\Column(name: 'PR_VALID_FROM', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['promotion:get'])]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(name: 'PR_VALID_TO', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['promotion:get'])]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(name: 'PR_ACTIVE')]
    #[Groups(['promotion:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'PR_CREATED_AT')]
    #[Groups(['promotion:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getDiscountTypesAsList(): array
    {
        return [self::DISCOUNT_PERCENT_OFF, self::DISCOUNT_FIXED_OFF];
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

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

    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    public function setDiscountType(string $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }

    public function getDiscountValue(): int
    {
        return $this->discountValue;
    }

    public function setDiscountValue(int $discountValue): static
    {
        $this->discountValue = $discountValue;

        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): static
    {
        $this->maxUses = $maxUses;

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

    public function getMaxUsesPerPhone(): ?int
    {
        return $this->maxUsesPerPhone;
    }

    public function setMaxUsesPerPhone(?int $maxUsesPerPhone): static
    {
        $this->maxUsesPerPhone = $maxUsesPerPhone;

        return $this;
    }

    public function getUsedCount(): int
    {
        return $this->usedCount;
    }

    public function setUsedCount(int $usedCount): static
    {
        $this->usedCount = $usedCount;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): static
    {
        $this->validTo = $validTo;

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
