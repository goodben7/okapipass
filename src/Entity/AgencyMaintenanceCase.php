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
use App\Dto\Agency\CompleteAgencyMaintenanceCaseDto;
use App\Dto\Agency\CreateAgencyMaintenanceCaseDto;
use App\Dto\Agency\UpdateAgencyMaintenanceCaseDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyMaintenanceCaseRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CancelAgencyMaintenanceCaseProcessor;
use App\State\Agency\CompleteAgencyMaintenanceCaseProcessor;
use App\State\Agency\CreateAgencyMaintenanceCaseProcessor;
use App\State\Agency\StartAgencyMaintenanceCaseProcessor;
use App\State\Agency\UpdateAgencyMaintenanceCaseProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyMaintenanceCaseRepository::class)]
#[ORM\Table(name: '`agency_maintenance_case`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyMaintenanceCase',
    normalizationContext: ['groups' => ['agency_maintenance:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/maintenance-cases',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/maintenance-cases/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/maintenance-cases',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyMaintenanceCaseDto::class,
            processor: CreateAgencyMaintenanceCaseProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/maintenance-cases/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyMaintenanceCaseDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyMaintenanceCaseProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/maintenance-cases/{id}/start',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: StartAgencyMaintenanceCaseProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/maintenance-cases/{id}/complete',
            security: 'is_granted("ROLE_PARTNER")',
            input: CompleteAgencyMaintenanceCaseDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: CompleteAgencyMaintenanceCaseProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/maintenance-cases/{id}/cancel',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: CancelAgencyMaintenanceCaseProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'type' => 'exact',
    'transport' => 'exact',
    'transport.id' => 'exact',
    'title' => 'ipartial',
])]
#[ApiFilter(DateFilter::class, properties: ['reportedAt', 'startedAt', 'completedAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'reportedAt', 'status'])]
class AgencyMaintenanceCase implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'MC';

    public const string TYPE_REPAIR = 'REPAIR';
    public const string TYPE_INSPECTION = 'INSPECTION';
    public const string TYPE_PREVENTIVE = 'PREVENTIVE';
    public const string TYPE_ACCIDENT = 'ACCIDENT';
    public const string TYPE_OTHER = 'OTHER';

    public const string STATUS_OPEN = 'OPEN';
    public const string STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const string STATUS_WAITING_PARTS = 'WAITING_PARTS';
    public const string STATUS_DONE = 'DONE';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'MC_ID', length: 16)]
    #[Groups(['agency_maintenance:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'MC_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_maintenance:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'MC_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_maintenance:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\Column(name: 'MC_TYPE', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getTypesAsList'])]
    #[Groups(['agency_maintenance:get'])]
    private ?string $type = null;

    #[ORM\Column(name: 'MC_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_maintenance:get'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'MC_TITLE', length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['agency_maintenance:get'])]
    private ?string $title = null;

    #[ORM\Column(name: 'MC_DESCRIPTION', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?string $description = null;

    #[ORM\Column(name: 'MC_REPORTED_AT')]
    #[Groups(['agency_maintenance:get'])]
    private ?\DateTimeImmutable $reportedAt = null;

    #[ORM\Column(name: 'MC_STARTED_AT', nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'MC_COMPLETED_AT', nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'MC_ODOMETER_KM', nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?int $odometerKm = null;

    #[ORM\Column(name: 'MC_ESTIMATED_COST', nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?int $estimatedCost = null;

    #[ORM\Column(name: 'MC_ACTUAL_COST', nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?int $actualCost = null;

    #[ORM\Column(name: 'MC_VENDOR_NAME', length: 120, nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?string $vendorName = null;

    #[ORM\Column(name: 'MC_CREATED_AT')]
    #[Groups(['agency_maintenance:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'MC_UPDATED_AT', nullable: true)]
    #[Groups(['agency_maintenance:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function getTypesAsList(): array
    {
        return [
            self::TYPE_REPAIR,
            self::TYPE_INSPECTION,
            self::TYPE_PREVENTIVE,
            self::TYPE_ACCIDENT,
            self::TYPE_OTHER,
        ];
    }

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_PARTS,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        ];
    }

    /** @return list<string> */
    public static function blockingStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_WAITING_PARTS,
        ];
    }

    public function isBlocking(): bool
    {
        return \in_array($this->status, self::blockingStatuses(), true);
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

    public function getTransport(): ?AgencyTransport
    {
        return $this->transport;
    }

    public function setTransport(?AgencyTransport $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getReportedAt(): ?\DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(\DateTimeImmutable $reportedAt): static
    {
        $this->reportedAt = $reportedAt;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

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

    public function getOdometerKm(): ?int
    {
        return $this->odometerKm;
    }

    public function setOdometerKm(?int $odometerKm): static
    {
        $this->odometerKm = $odometerKm;

        return $this;
    }

    public function getEstimatedCost(): ?int
    {
        return $this->estimatedCost;
    }

    public function setEstimatedCost(?int $estimatedCost): static
    {
        $this->estimatedCost = $estimatedCost;

        return $this;
    }

    public function getActualCost(): ?int
    {
        return $this->actualCost;
    }

    public function setActualCost(?int $actualCost): static
    {
        $this->actualCost = $actualCost;

        return $this;
    }

    public function getVendorName(): ?string
    {
        return $this->vendorName;
    }

    public function setVendorName(?string $vendorName): static
    {
        $this->vendorName = $vendorName;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
        $this->reportedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
