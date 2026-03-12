<?php

namespace App\Entity;

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
use App\Dto\CreateAgencyDto;
use App\Dto\UpdateAgencyDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyRepository;
use App\State\CreateAgencyProcessor;
use App\State\UpdateAgencyProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyRepository::class)] 
#[ORM\Table(name: '`agency`')]
#[ApiResource(
    normalizationContext: ['groups' => 'agency:get'],
    operations: [
        new Get(
            security: 'is_granted("ROLE_AGENCY_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            security: 'is_granted("ROLE_AGENCY_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_AGENCY_CREATE")',
            input: CreateAgencyDto::class,
            processor: CreateAgencyProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_AGENCY_UPDATE")',
            input: UpdateAgencyDto::class,
            processor: UpdateAgencyProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'name' => 'ipartial',
    'email' => 'exact',
    'phone' => 'exact',
    'type' => 'exact',
    'status' => 'exact',
    'createdBy.id' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'name'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class Agency implements RessourceInterface
{
    public const string ID_PREFIX = 'AG';

    public const string TYPE_ROAD = 'ROAD';
    public const string TYPE_LAKE = 'LAKE';
    public const string TYPE_RIVER = 'RIVER';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_INACTIVE = 'INACTIVE';

    public const string EVENT_AGENCY_CREATED = 'agency.created';
    public const string EVENT_AGENCY_UPDATED = 'agency.updated';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AG_ID', length: 16)]
    #[Groups(['agency:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'AG_NAME', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agency:get'])]
    private ?string $name = null;

    #[ORM\Column(name: 'AG_EMAIL', length: 180, nullable: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['agency:get'])]
    private ?string $email = null;

    #[ORM\Column(name: 'AG_PHONE', length: 15, nullable: true)]
    #[Assert\Length(max: 15)]
    #[Groups(['agency:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'AG_ADDRESS', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['agency:get'])]
    private ?string $address = null;

    #[ORM\Column(name: 'AG_TYPE', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getTypesAsList'])]
    #[Groups(['agency:get'])]
    private ?string $type = self::TYPE_ROAD;

    #[ORM\Column(name: 'AG_STATUS', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency:get'])]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'AG_USER_ID', length: 16, nullable: true)]
    #[Groups(['agency:get'])]
    private ?string $userId = null;

    #[ORM\Column(name: 'AG_CREATED_AT')]
    #[Groups(['agency:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AG_CREATED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['agency:get'])]
    private ?User $createdBy = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
        ];
    }

    public static function getTypesAsList(): array
    {
        return [
            self::TYPE_ROAD,
            self::TYPE_LAKE,
            self::TYPE_RIVER,
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Get the value of userId
     */ 
    public function getUserId(): string|null
    {
        return $this->userId;
    }

    /**
     * Set the value of userId
     *
     * @return  self
     */ 
    public function setUserId(string|null $userId): static
    {
        $this->userId = $userId;

        return $this;
    }
}
