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
use App\Dto\Agency\CreateAgencyFleetIncidentDto;
use App\Dto\Agency\UpdateAgencyFleetIncidentDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyFleetIncidentRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyFleetIncidentProcessor;
use App\State\Agency\ResolveAgencyFleetIncidentProcessor;
use App\State\Agency\UpdateAgencyFleetIncidentProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyFleetIncidentRepository::class)]
#[ORM\Table(name: '`agency_fleet_incident`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyFleetIncident',
    normalizationContext: ['groups' => ['agency_fleet_incident:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/fleet/incidents',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/fleet/incidents/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/fleet/incidents',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyFleetIncidentDto::class,
            processor: CreateAgencyFleetIncidentProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/fleet/incidents/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyFleetIncidentDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyFleetIncidentProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/fleet/incidents/{id}/resolve',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ResolveAgencyFleetIncidentProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'type' => 'exact',
    'severity' => 'exact',
    'transport.id' => 'exact',
    'driver.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['occurredAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['occurredAt', 'createdAt', 'status'])]
class AgencyFleetIncident implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'FI';

    public const string TYPE_BREAKDOWN = 'BREAKDOWN';
    public const string TYPE_ACCIDENT = 'ACCIDENT';
    public const string TYPE_DELAY = 'DELAY';
    public const string TYPE_OTHER = 'OTHER';

    public const string SEVERITY_LOW = 'LOW';
    public const string SEVERITY_MEDIUM = 'MEDIUM';
    public const string SEVERITY_HIGH = 'HIGH';

    public const string STATUS_OPEN = 'OPEN';
    public const string STATUS_RESOLVED = 'RESOLVED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'FI_ID', length: 16)]
    #[Groups(['agency_fleet_incident:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'FI_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_fleet_incident:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'FI_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_fleet_incident:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'FI_DRIVER', nullable: true, referencedColumnName: 'AD_ID')]
    #[Groups(['agency_fleet_incident:get'])]
    private ?AgencyDriver $driver = null;

    #[ORM\Column(name: 'FI_TYPE', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getTypesAsList'])]
    #[Groups(['agency_fleet_incident:get'])]
    private ?string $type = null;

    #[ORM\Column(name: 'FI_SEVERITY', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getSeveritiesAsList'])]
    #[Groups(['agency_fleet_incident:get'])]
    private ?string $severity = null;

    #[ORM\Column(name: 'FI_LAT', nullable: true)]
    #[Groups(['agency_fleet_incident:get'])]
    private ?float $lat = null;

    #[ORM\Column(name: 'FI_LNG', nullable: true)]
    #[Groups(['agency_fleet_incident:get'])]
    private ?float $lng = null;

    #[ORM\Column(name: 'FI_PHOTO_URL', length: 512, nullable: true)]
    #[Groups(['agency_fleet_incident:get'])]
    private ?string $photoUrl = null;

    #[ORM\Column(name: 'FI_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_fleet_incident:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'FI_OCCURRED_AT')]
    #[Groups(['agency_fleet_incident:get'])]
    private ?\DateTimeImmutable $occurredAt = null;

    #[ORM\Column(name: 'FI_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_fleet_incident:get'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'FI_CREATED_AT')]
    #[Groups(['agency_fleet_incident:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'FI_UPDATED_AT', nullable: true)]
    #[Groups(['agency_fleet_incident:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @return list<string> */
    public static function getTypesAsList(): array
    {
        return [self::TYPE_BREAKDOWN, self::TYPE_ACCIDENT, self::TYPE_DELAY, self::TYPE_OTHER];
    }

    /** @return list<string> */
    public static function getSeveritiesAsList(): array
    {
        return [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH];
    }

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_OPEN, self::STATUS_RESOLVED];
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

    public function getDriver(): ?AgencyDriver
    {
        return $this->driver;
    }

    public function setDriver(?AgencyDriver $driver): static
    {
        $this->driver = $driver;

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

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function setLng(?float $lng): static
    {
        $this->lng = $lng;

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): static
    {
        $this->photoUrl = $photoUrl;

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

    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

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
