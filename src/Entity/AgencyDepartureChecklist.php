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
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAgencyDepartureChecklistDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyDepartureChecklistRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyDepartureChecklistProcessor;
use App\State\Agency\SubmitAgencyDepartureChecklistProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyDepartureChecklistRepository::class)]
#[ORM\Table(name: '`agency_departure_checklist`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyDepartureChecklist',
    normalizationContext: ['groups' => ['agency_departure_checklist:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/fleet/departures/checklists',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/fleet/departures/checklists/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/fleet/departures/checklists',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyDepartureChecklistDto::class,
            processor: CreateAgencyDepartureChecklistProcessor::class,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/fleet/departures/checklists/{id}/submit',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: SubmitAgencyDepartureChecklistProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'transport.id' => 'exact',
    'offer.id' => 'exact',
    'driver.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['travelDate', 'createdAt', 'submittedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['travelDate', 'createdAt'])]
class AgencyDepartureChecklist implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'CK';

    public const string STATUS_DRAFT = 'DRAFT';
    public const string STATUS_SUBMITTED = 'SUBMITTED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'DC_ID', length: 16)]
    #[Groups(['agency_departure_checklist:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DC_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_departure_checklist:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DC_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_departure_checklist:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DC_DRIVER', nullable: true, referencedColumnName: 'AD_ID')]
    #[Groups(['agency_departure_checklist:get'])]
    private ?AgencyDriver $driver = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DC_OFFER', nullable: true, referencedColumnName: 'AO_ID')]
    #[Groups(['agency_departure_checklist:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'DC_TRAVEL_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['agency_departure_checklist:get'])]
    private ?\DateTimeImmutable $travelDate = null;

    #[ORM\Column(name: 'DC_ODOMETER_KM', nullable: true)]
    #[Groups(['agency_departure_checklist:get'])]
    private ?int $odometerKm = null;

    #[ORM\Column(name: 'DC_FUEL_LEVEL_PERCENT', nullable: true)]
    #[Groups(['agency_departure_checklist:get'])]
    private ?int $fuelLevelPercent = null;

    #[ORM\Column(name: 'DC_VEHICLE_OK')]
    #[Groups(['agency_departure_checklist:get'])]
    private bool $vehicleOk = true;

    #[ORM\Column(name: 'DC_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_departure_checklist:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'DC_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_departure_checklist:get'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'DC_SUBMITTED_AT', nullable: true)]
    #[Groups(['agency_departure_checklist:get'])]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DC_CREATED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['agency_departure_checklist:get'])]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'DC_CREATED_AT')]
    #[Groups(['agency_departure_checklist:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_SUBMITTED];
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

    public function getOffer(): ?AgencyOffer
    {
        return $this->offer;
    }

    public function setOffer(?AgencyOffer $offer): static
    {
        $this->offer = $offer;

        return $this;
    }

    public function getTravelDate(): ?\DateTimeImmutable
    {
        return $this->travelDate;
    }

    public function setTravelDate(\DateTimeImmutable $travelDate): static
    {
        $this->travelDate = $travelDate;

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

    public function getFuelLevelPercent(): ?int
    {
        return $this->fuelLevelPercent;
    }

    public function setFuelLevelPercent(?int $fuelLevelPercent): static
    {
        $this->fuelLevelPercent = $fuelLevelPercent;

        return $this;
    }

    public function isVehicleOk(): bool
    {
        return $this->vehicleOk;
    }

    public function setVehicleOk(bool $vehicleOk): static
    {
        $this->vehicleOk = $vehicleOk;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

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
