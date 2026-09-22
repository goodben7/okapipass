<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\AgencyObligationTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyObligationTypeRepository::class)]
#[ORM\Table(name: '`agency_obligation_type`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_OBLIGATION_TYPE_CODE', fields: ['code'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyObligationType',
    normalizationContext: ['groups' => ['agency_obligation_type:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/obligation-types',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/obligation-types/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: ItemProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'code' => 'exact',
    'category' => 'exact',
    'label' => 'ipartial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(OrderFilter::class, properties: ['sortOrder', 'label'])]
class AgencyObligationType implements RessourceInterface
{
    public const string ID_PREFIX = 'OT';

    public const string CATEGORY_LICENSE = 'LICENSE';
    public const string CATEGORY_INSURANCE = 'INSURANCE';
    public const string CATEGORY_TECHNICAL = 'TECHNICAL';
    public const string CATEGORY_TAX = 'TAX';
    public const string CATEGORY_FPT = 'FPT';
    public const string CATEGORY_OTHER = 'OTHER';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AOT_ID', length: 16)]
    #[Groups(['agency_obligation_type:get', 'agency_obligation:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'AOT_CODE', length: 40)]
    #[Assert\NotBlank]
    #[Groups(['agency_obligation_type:get', 'agency_obligation:get'])]
    private ?string $code = null;

    #[ORM\Column(name: 'AOT_LABEL', length: 160)]
    #[Groups(['agency_obligation_type:get', 'agency_obligation:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'AOT_DESCRIPTION', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_obligation_type:get'])]
    private ?string $description = null;

    #[ORM\Column(name: 'AOT_CATEGORY', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getCategoriesAsList'])]
    #[Groups(['agency_obligation_type:get', 'agency_obligation:get'])]
    private string $category = self::CATEGORY_OTHER;

    #[ORM\Column(name: 'AOT_DEFAULT_VALIDITY_MONTHS', nullable: true)]
    #[Groups(['agency_obligation_type:get'])]
    private ?int $defaultValidityMonths = null;

    #[ORM\Column(name: 'AOT_REMINDER_DAYS')]
    #[Groups(['agency_obligation_type:get'])]
    private int $reminderDays = 30;

    #[ORM\Column(name: 'AOT_ACTIVE')]
    #[Groups(['agency_obligation_type:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'AOT_SORT_ORDER')]
    #[Groups(['agency_obligation_type:get'])]
    private int $sortOrder = 100;

    #[ORM\Column(name: 'AOT_CREATED_AT')]
    #[Groups(['agency_obligation_type:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getCategoriesAsList(): array
    {
        return [
            self::CATEGORY_LICENSE,
            self::CATEGORY_INSURANCE,
            self::CATEGORY_TECHNICAL,
            self::CATEGORY_TAX,
            self::CATEGORY_FPT,
            self::CATEGORY_OTHER,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getDefaultValidityMonths(): ?int
    {
        return $this->defaultValidityMonths;
    }

    public function setDefaultValidityMonths(?int $defaultValidityMonths): static
    {
        $this->defaultValidityMonths = $defaultValidityMonths;

        return $this;
    }

    public function getReminderDays(): int
    {
        return $this->reminderDays;
    }

    public function setReminderDays(int $reminderDays): static
    {
        $this->reminderDays = max(0, $reminderDays);

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

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
