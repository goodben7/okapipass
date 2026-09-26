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
use App\Dto\Agency\CreateAgencyFuelLogDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyFuelLogRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyFuelLogProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyFuelLogRepository::class)]
#[ORM\Table(name: '`agency_fuel_log`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyFuelLog',
    normalizationContext: ['groups' => ['agency_fuel_log:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/fleet/fuel-logs',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/fleet/fuel-logs/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/fleet/fuel-logs',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyFuelLogDto::class,
            processor: CreateAgencyFuelLogProcessor::class,
            status: 201,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'transport' => 'exact',
    'transport.id' => 'exact',
    'driver.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['fueledAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['fueledAt', 'createdAt'])]
class AgencyFuelLog implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'FL';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'FL_ID', length: 16)]
    #[Groups(['agency_fuel_log:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'FL_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_fuel_log:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'FL_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_fuel_log:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'FL_DRIVER', nullable: true, referencedColumnName: 'AD_ID')]
    #[Groups(['agency_fuel_log:get'])]
    private ?AgencyDriver $driver = null;

    #[ORM\Column(name: 'FL_LITERS')]
    #[Assert\Positive]
    #[Groups(['agency_fuel_log:get'])]
    private ?int $liters = null;

    #[ORM\Column(name: 'FL_AMOUNT')]
    #[Assert\PositiveOrZero]
    #[Groups(['agency_fuel_log:get'])]
    private ?int $amount = null;

    #[ORM\Column(name: 'FL_CURRENCY', length: 3)]
    #[Groups(['agency_fuel_log:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'FL_ODOMETER_KM', nullable: true)]
    #[Groups(['agency_fuel_log:get'])]
    private ?int $odometerKm = null;

    #[ORM\Column(name: 'FL_FUELED_AT')]
    #[Groups(['agency_fuel_log:get'])]
    private ?\DateTimeImmutable $fueledAt = null;

    #[ORM\Column(name: 'FL_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_fuel_log:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'FL_CREATED_AT')]
    #[Groups(['agency_fuel_log:get'])]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getLiters(): ?int
    {
        return $this->liters;
    }

    public function setLiters(int $liters): static
    {
        $this->liters = $liters;

        return $this;
    }

    public function getAmount(): ?int
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

    public function getOdometerKm(): ?int
    {
        return $this->odometerKm;
    }

    public function setOdometerKm(?int $odometerKm): static
    {
        $this->odometerKm = $odometerKm;

        return $this;
    }

    public function getFueledAt(): ?\DateTimeImmutable
    {
        return $this->fueledAt;
    }

    public function setFueledAt(\DateTimeImmutable $fueledAt): static
    {
        $this->fueledAt = $fueledAt;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
        $this->fueledAt ??= $this->createdAt;
    }
}
