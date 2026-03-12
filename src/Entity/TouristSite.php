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
use App\Repository\TouristSiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TouristSiteRepository::class)] 
#[ORM\Table(name: '`tourist_site`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => 'tourist_site:get'],
    operations: [
        new Get(
            //security: 'is_granted("ROLE_TOURIST_SITE_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            //security: 'is_granted("ROLE_TOURIST_SITE_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_TOURIST_SITE_CREATE")',
            denormalizationContext: ['groups' => 'tourist_site:post'],
            processor: PersistProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_TOURIST_SITE_UPDATE")',
            denormalizationContext: ['groups' => 'tourist_site:patch'],
            processor: PersistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'name' => 'ipartial',
    'city.id' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'entryPrice', 'name'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class TouristSite implements RessourceInterface
{
    public const string ID_PREFIX = 'TS';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_INACTIVE = 'INACTIVE';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TS_ID', length: 16)]
    #[Groups(['tourist_site:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'TS_NAME', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $name = null;

    #[ORM\Column(name: 'TS_DESCRIPTION', type: Types::TEXT, nullable: true)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TS_CITY', nullable: true, referencedColumnName: 'CP_ID')]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?Checkpoint $city = null;

    #[ORM\Column(name: 'TS_ADDRESS', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $address = null;

    #[ORM\Column(name: 'TS_LATITUDE', type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $latitude = null;

    #[ORM\Column(name: 'TS_LONGITUDE', type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $longitude = null;

    #[ORM\Column(name: 'TS_ENTRY_PRICE', type: Types::DECIMAL, precision: 17, scale: 2, nullable: true)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $entryPrice = null;

    #[ORM\Column(name: 'TS_OPENING_HOURS', type: Types::TEXT, nullable: true)]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $openingHours = null;

    #[ORM\Column(name: 'TS_STATUS', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['tourist_site:get', 'tourist_site:post', 'tourist_site:patch'])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'TS_CREATED_AT')]
    #[Groups(['tourist_site:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCity(): ?Checkpoint
    {
        return $this->city;
    }

    public function setCity(?Checkpoint $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getEntryPrice(): ?string
    {
        return $this->entryPrice;
    }

    public function setEntryPrice(?string $entryPrice): static
    {
        $this->entryPrice = $entryPrice;

        return $this;
    }

    public function getOpeningHours(): ?string
    {
        return $this->openingHours;
    }

    public function setOpeningHours(?string $openingHours): static
    {
        $this->openingHours = $openingHours;

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
