<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection; 
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\TripRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ORM\Table(name: '`trip`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => 'trip:get'],
    operations: [
        new Get(
            //security: 'is_granted("ROLE_TRIP_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            //security: 'is_granted("ROLE_TRIP_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_TRIP_CREATE")',
            denormalizationContext: ['groups' => 'trip:post'],
            processor: PersistProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_TRIP_UPDATE")',
            denormalizationContext: ['groups' => 'trip:patch'],
            processor: PersistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'label' => 'ipartial',
    'status' => 'exact',
    'agency.id' => 'exact',
    'departure.id' => 'exact',
    'arrival.id' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'departureTime', 'arrivalTime', 'price'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'departureTime', 'arrivalTime'])]
class Trip implements RessourceInterface
{
    public const string ID_PREFIX = 'TR';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_INACTIVE = 'INACTIVE';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TR_ID', length: 16)]
    #[Groups(['trip:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'TR_LABEL', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?string $label = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TR_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Assert\NotNull]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TR_DEPARTURE', nullable: false, referencedColumnName: 'CP_ID')]
    #[Assert\NotNull]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?Checkpoint $departure = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TR_ARRIVAL', nullable: false, referencedColumnName: 'CP_ID')]
    #[Assert\NotNull]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?Checkpoint $arrival = null;

    #[ORM\Column(name: 'TR_DEPARTURE_TIME')]
    #[Assert\NotNull]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?\DateTimeImmutable $departureTime = null;

    #[ORM\Column(name: 'TR_ARRIVAL_TIME')]
    #[Assert\NotNull]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?\DateTimeImmutable $arrivalTime = null;

    #[ORM\Column(name: 'TR_PRICE', type: Types::DECIMAL, precision: 17, scale: 2)]
    #[Assert\NotNull]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?string $price = null;

    #[ORM\Column(name: 'TR_STATUS', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['trip:get', 'trip:post', 'trip:patch'])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'TR_CREATED_AT')]
    #[Groups(['trip:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_CANCELLED,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getDeparture(): ?Checkpoint
    {
        return $this->departure;
    }

    public function setDeparture(Checkpoint $departure): static
    {
        $this->departure = $departure;

        return $this;
    }

    public function getArrival(): ?Checkpoint
    {
        return $this->arrival;
    }

    public function setArrival(Checkpoint $arrival): static
    {
        $this->arrival = $arrival;

        return $this;
    }

    public function getDepartureTime(): ?\DateTimeImmutable
    {
        return $this->departureTime;
    }

    public function setDepartureTime(\DateTimeImmutable $departureTime): static
    {
        $this->departureTime = $departureTime;

        return $this;
    }

    public function getArrivalTime(): ?\DateTimeImmutable
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(\DateTimeImmutable $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStatus(): ?string
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

    #[ORM\PrePersist]
    public function buildCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }
}
