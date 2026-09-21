<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use App\Dto\Agency\CreateAgencyObligationDto;
use App\Dto\Agency\UpdateAgencyObligationDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyObligationRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CompleteAgencyObligationProcessor;
use App\State\Agency\CreateAgencyObligationProcessor;
use App\State\Agency\UpdateAgencyObligationProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyObligationRepository::class)]
#[ORM\Table(name: '`agency_obligation`')]
#[ORM\Index(name: 'IDX_AGENCY_OBLIGATION_DUE', columns: ['AOB_AGENCY', 'AOB_DUE_DATE'])]
#[ORM\Index(name: 'IDX_AGENCY_OBLIGATION_STATUS', columns: ['AOB_AGENCY', 'AOB_STATUS'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyObligation',
    normalizationContext: ['groups' => ['agency_obligation:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/obligations',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/obligations/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/obligations',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyObligationDto::class,
            processor: CreateAgencyObligationProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/obligations/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyObligationDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyObligationProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/obligations/{id}/complete',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: CompleteAgencyObligationProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'title' => 'ipartial',
    'type.code' => 'exact',
    'type.category' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['dueDate'])]
#[ApiFilter(OrderFilter::class, properties: ['dueDate', 'createdAt', 'title'])]
class AgencyObligation implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'OB';

    public const string STATUS_OPEN = 'OPEN';
    public const string STATUS_COMPLETED = 'COMPLETED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    public const string URGENCY_UPCOMING = 'UPCOMING';
    public const string URGENCY_DUE_SOON = 'DUE_SOON';
    public const string URGENCY_OVERDUE = 'OVERDUE';
    public const string URGENCY_DONE = 'DONE';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AOB_ID', length: 16)]
    #[Groups(['agency_obligation:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AOB_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_obligation:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AOB_TYPE', nullable: true, referencedColumnName: 'AOT_ID')]
    #[Groups(['agency_obligation:get'])]
    private ?AgencyObligationType $type = null;

    #[ORM\Column(name: 'AOB_TITLE', length: 160)]
    #[Groups(['agency_obligation:get'])]
    private ?string $title = null;

    #[ORM\Column(name: 'AOB_REFERENCE', length: 80, nullable: true)]
    #[Groups(['agency_obligation:get'])]
    private ?string $reference = null;

    #[ORM\Column(name: 'AOB_DUE_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['agency_obligation:get'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(name: 'AOB_STATUS', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_obligation:get'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'AOB_REMINDER_DAYS')]
    #[Groups(['agency_obligation:get'])]
    private int $reminderDays = 30;

    #[ORM\Column(name: 'AOB_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_obligation:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'AOB_COMPLETED_AT', nullable: true)]
    #[Groups(['agency_obligation:get'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'AOB_CREATED_AT')]
    #[Groups(['agency_obligation:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AOB_UPDATED_AT', nullable: true)]
    #[Groups(['agency_obligation:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function getStatusesAsList(): array
    {
        return [self::STATUS_OPEN, self::STATUS_COMPLETED, self::STATUS_CANCELLED];
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

    public function getType(): ?AgencyObligationType
    {
        return $this->type;
    }

    public function setType(?AgencyObligationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate->setTime(0, 0);

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

    public function getReminderDays(): int
    {
        return $this->reminderDays;
    }

    public function setReminderDays(int $reminderDays): static
    {
        $this->reminderDays = max(0, $reminderDays);

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

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

    #[Groups(['agency_obligation:get'])]
    #[SerializedName('urgency')]
    public function getUrgency(?\DateTimeImmutable $at = null): string
    {
        if (self::STATUS_COMPLETED === $this->status || self::STATUS_CANCELLED === $this->status) {
            return self::URGENCY_DONE;
        }

        $at ??= new \DateTimeImmutable('today');
        $due = $this->dueDate;
        if (null === $due) {
            return self::URGENCY_UPCOMING;
        }

        if ($due < $at) {
            return self::URGENCY_OVERDUE;
        }

        $soonLimit = $at->modify(sprintf('+%d days', $this->reminderDays));
        if ($due <= $soonLimit) {
            return self::URGENCY_DUE_SOON;
        }

        return self::URGENCY_UPCOMING;
    }

    #[Groups(['agency_obligation:get'])]
    #[SerializedName('daysRemaining')]
    public function getDaysRemaining(?\DateTimeImmutable $at = null): ?int
    {
        if (null === $this->dueDate) {
            return null;
        }
        $at ??= new \DateTimeImmutable('today');
        $diff = (int) $at->diff($this->dueDate)->format('%r%a');

        return $diff;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
