<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAgencyTransportDto;
use App\Dto\Agency\UpdateAgencyTransportDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyTransportRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyTransportProcessor;
use App\State\Agency\DeleteAgencyTransportProcessor;
use App\State\Agency\UpdateAgencyTransportProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyTransportRepository::class)]
#[ORM\Table(name: '`agency_transport`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_TRANSPORT_PLATE', fields: ['agency', 'plateNumber'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyTransport',
    normalizationContext: ['groups' => ['agency_transport:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/transports',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/transports/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/transports',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyTransportDto::class,
            processor: CreateAgencyTransportProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/transports/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyTransportDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyTransportProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/transports/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyTransportProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'label' => 'ipartial',
    'kind' => 'exact',
    'plateNumber' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'label', 'capacity'])]
class AgencyTransport implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AT';

    public const string KIND_BUS = 'BUS';
    public const string KIND_MINIBUS = 'MINIBUS';
    public const string KIND_COASTER = 'COASTER';
    public const string KIND_VAN = 'VAN';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_INACTIVE = 'INACTIVE';
    public const string STATUS_MAINTENANCE = 'MAINTENANCE';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AT_ID', length: 16)]
    #[Groups(['agency_transport:get', 'agency_offer:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AT_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_transport:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'AT_LABEL', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agency_transport:get', 'agency_offer:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'AT_KIND', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getKindsAsList'])]
    #[Groups(['agency_transport:get', 'agency_offer:get'])]
    private ?string $kind = null;

    #[ORM\Column(name: 'AT_PLATE_NUMBER', length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    #[Groups(['agency_transport:get', 'agency_offer:get'])]
    private ?string $plateNumber = null;

    #[ORM\Column(name: 'AT_CAPACITY')]
    #[Assert\Positive]
    #[Groups(['agency_transport:get', 'agency_offer:get'])]
    private ?int $capacity = null;

    #[ORM\Column(name: 'AT_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_transport:get', 'agency_offer:get'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'AT_CREATED_AT')]
    #[Groups(['agency_transport:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AT_UPDATED_AT', nullable: true)]
    #[Groups(['agency_transport:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function getKindsAsList(): array
    {
        return [
            self::KIND_BUS,
            self::KIND_MINIBUS,
            self::KIND_COASTER,
            self::KIND_VAN,
        ];
    }

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_MAINTENANCE,
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

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(string $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getPlateNumber(): ?string
    {
        return $this->plateNumber;
    }

    public function setPlateNumber(string $plateNumber): static
    {
        $this->plateNumber = strtoupper(trim($plateNumber));

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

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

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActiveForSale(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
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
