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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAgencyWorkOrderDto;
use App\Dto\Agency\UpdateAgencyWorkOrderDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyWorkOrderRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CancelAgencyWorkOrderProcessor;
use App\State\Agency\CompleteAgencyWorkOrderProcessor;
use App\State\Agency\CreateAgencyWorkOrderProcessor;
use App\State\Agency\StartAgencyWorkOrderProcessor;
use App\State\Agency\UpdateAgencyWorkOrderProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyWorkOrderRepository::class)]
#[ORM\Table(name: '`agency_work_order`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyWorkOrder',
    normalizationContext: ['groups' => ['agency_work_order:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/fleet/maintenance/work-orders',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/fleet/maintenance/work-orders/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/fleet/maintenance/work-orders',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyWorkOrderDto::class,
            processor: CreateAgencyWorkOrderProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/fleet/maintenance/work-orders/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyWorkOrderDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyWorkOrderProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/fleet/maintenance/work-orders/{id}/start',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: StartAgencyWorkOrderProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/fleet/maintenance/work-orders/{id}/complete',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: CompleteAgencyWorkOrderProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/fleet/maintenance/work-orders/{id}/cancel',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: CancelAgencyWorkOrderProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'transport.id' => 'exact',
    'maintenanceCase.id' => 'exact',
    'title' => 'ipartial',
    'immobilize' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['startedAt', 'completedAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'status'])]
class AgencyWorkOrder implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'WO';

    public const string STATUS_OPEN = 'OPEN';
    public const string STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const string STATUS_DONE = 'DONE';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'WO_ID', length: 16)]
    #[Groups(['agency_work_order:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WO_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_work_order:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WO_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_work_order:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WO_MAINTENANCE_CASE', nullable: true, referencedColumnName: 'MC_ID')]
    #[Groups(['agency_work_order:get'])]
    private ?AgencyMaintenanceCase $maintenanceCase = null;

    #[ORM\Column(name: 'WO_TITLE', length: 160)]
    #[Assert\NotBlank]
    #[Groups(['agency_work_order:get'])]
    private ?string $title = null;

    #[ORM\Column(name: 'WO_DESCRIPTION', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_work_order:get'])]
    private ?string $description = null;

    #[ORM\Column(name: 'WO_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_work_order:get'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'WO_PARTS_COST', options: ['default' => 0])]
    #[Groups(['agency_work_order:get'])]
    private int $partsCost = 0;

    #[ORM\Column(name: 'WO_LABOR_COST', options: ['default' => 0])]
    #[Groups(['agency_work_order:get'])]
    private int $laborCost = 0;

    #[ORM\Column(name: 'WO_IMMOBILIZE')]
    #[Groups(['agency_work_order:get'])]
    private bool $immobilize = false;

    #[ORM\Column(name: 'WO_VENDOR_NAME', length: 120, nullable: true)]
    #[Groups(['agency_work_order:get'])]
    private ?string $vendorName = null;

    #[ORM\Column(name: 'WO_STARTED_AT', nullable: true)]
    #[Groups(['agency_work_order:get'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'WO_COMPLETED_AT', nullable: true)]
    #[Groups(['agency_work_order:get'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'WO_CREATED_AT')]
    #[Groups(['agency_work_order:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'WO_UPDATED_AT', nullable: true)]
    #[Groups(['agency_work_order:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
        ];
    }

    /** @return list<string> */
    public static function openStatuses(): array
    {
        return [self::STATUS_OPEN, self::STATUS_IN_PROGRESS];
    }

    public function isImmobilizingOpen(): bool
    {
        return $this->immobilize && \in_array($this->status, self::openStatuses(), true);
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

    public function getMaintenanceCase(): ?AgencyMaintenanceCase
    {
        return $this->maintenanceCase;
    }

    public function setMaintenanceCase(?AgencyMaintenanceCase $maintenanceCase): static
    {
        $this->maintenanceCase = $maintenanceCase;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPartsCost(): int
    {
        return $this->partsCost;
    }

    public function setPartsCost(int $partsCost): static
    {
        $this->partsCost = $partsCost;

        return $this;
    }

    public function getLaborCost(): int
    {
        return $this->laborCost;
    }

    public function setLaborCost(int $laborCost): static
    {
        $this->laborCost = $laborCost;

        return $this;
    }

    public function isImmobilize(): bool
    {
        return $this->immobilize;
    }

    public function setImmobilize(bool $immobilize): static
    {
        $this->immobilize = $immobilize;

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
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
