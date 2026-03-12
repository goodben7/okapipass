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
use App\Repository\HotelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HotelRepository::class)] 
#[ORM\Table(name: '`hotel`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => 'hotel:get'],
    operations: [
        new Get(
            security: 'is_granted("ROLE_HOTEL_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            security: 'is_granted("ROLE_HOTEL_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_HOTEL_CREATE")',
            denormalizationContext: ['groups' => 'hotel:post'],
            processor: PersistProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_HOTEL_UPDATE")',
            denormalizationContext: ['groups' => 'hotel:patch'],
            processor: PersistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'name' => 'ipartial',
    'phone' => 'exact',
    'email' => 'exact',
    'city.id' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'rating', 'price', 'name'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class Hotel implements RessourceInterface
{
    public const string ID_PREFIX = 'HO';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_INACTIVE = 'INACTIVE';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'HO_ID', length: 16)]
    #[Groups(['hotel:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'HO_NAME', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $name = null;

    #[ORM\Column(name: 'HO_DESCRIPTION', type: Types::TEXT, nullable: true)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $description = null;

    #[ORM\Column(name: 'HO_PHONE', length: 15, nullable: true)]
    #[Assert\Length(max: 15)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'HO_EMAIL', length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $email = null;

    #[ORM\Column(name: 'HO_ADDRESS', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $address = null;

    #[ORM\Column(name: 'HO_LATITUDE', type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $latitude = null;

    #[ORM\Column(name: 'HO_LONGITUDE', type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $longitude = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'HO_CITY', nullable: true, referencedColumnName: 'CP_ID')]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?Checkpoint $city = null;

    #[ORM\Column(name: 'HO_PRICE', type: Types::DECIMAL, precision: 17, scale: 2, nullable: true)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $price = null;

    #[ORM\Column(name: 'HO_RATING', type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?int $rating = null;

    #[ORM\Column(name: 'HO_STATUS', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['hotel:get', 'hotel:post', 'hotel:patch'])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'HO_CREATED_AT')]
    #[Groups(['hotel:get'])]
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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

    public function getCity(): ?Checkpoint
    {
        return $this->city;
    }

    public function setCity(?Checkpoint $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

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
