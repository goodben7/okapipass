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
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Dto\CreateTicketDto;
use App\Model\RessourceInterface;
use App\Repository\TicketRepository;
use App\State\CreateTicketProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: '`ticket`')]
#[ORM\UniqueConstraint(name: 'UNIQ_TICKET_UNIQUE_REFERENCE', fields: ['uniqueReference'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => 'ticket:get'],
    operations: [
        new Get(
            //security: 'is_granted("ROLE_TICKET_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            //security: 'is_granted("ROLE_TICKET_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_TICKET_CREATE")',
            input: CreateTicketDto::class,
            processor: CreateTicketProcessor::class,
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'displayName' => 'ipartial',
    'phone' => 'exact',
    'status' => 'exact',
    'goPass.id' => 'exact',
    'departure.id' => 'exact',
    'arrival.id' => 'exact',
    'issuedBy.id' => 'exact',
    'identifier' => 'exact',
    'uniqueReference' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['issuedAt', 'validatedAt'])]
#[ApiFilter(DateFilter::class, properties: ['issuedAt', 'validatedAt'])]
class Ticket implements RessourceInterface
{
    public const string ID_PREFIX = 'TI';

    public const string STATUS_ISSUED = 'ISSUED';
    public const string STATUS_VALIDATED = 'VALIDATED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    public const string EVENT_TICKET_CREATED = "ticket.created";

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TI_ID', length: 16)]
    #[Groups(['ticket:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'TI_DISPLAY_NAME', length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['ticket:get'])]
    private ?string $displayName = null;

    #[ORM\Column(name: 'TI_PHONE', length: 15, nullable: true)]
    #[Assert\Length(max: 15)]
    #[Groups(['ticket:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'TI_IDENTIFIER', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['ticket:get'])]
    private ?string $identifier = null;

    #[ORM\Column(name: 'TI_STATUS', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['ticket:get'])]
    private ?string $status = self::STATUS_ISSUED;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TI_GOPASS', nullable: false, referencedColumnName: 'GP_ID')]
    #[Assert\NotNull]
    #[Groups(['ticket:get'])]
    private ?GoPass $goPass = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TI_DEPARTURE', nullable: false, referencedColumnName: 'CP_ID')]
    #[Assert\NotNull]
    #[Groups(['ticket:get'])]
    private ?Checkpoint $departure = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TI_ARRIVAL', nullable: false, referencedColumnName: 'CP_ID')]
    #[Assert\NotNull]
    #[Groups(['ticket:get'])]
    private ?Checkpoint $arrival = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TI_ISSUED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['ticket:get'])]
    private ?User $issuedBy = null;

    #[ORM\Column(name: 'TI_ISSUED_AT')]
    #[Groups(['ticket:get'])]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(name: 'TI_UNIQUE_REFERENCE', length: 8, nullable: true)]
    #[Assert\Length(max: 8)]
    #[Groups(['ticket:get'])]
    private ?string $uniqueReference = null;

    #[ORM\Column(name: 'TI_VALIDATED_AT', nullable: true)]
    #[Groups(['ticket:get'])]
    private ?\DateTimeImmutable $validatedAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ISSUED,
            self::STATUS_VALIDATED,
            self::STATUS_CANCELLED,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getGoPass(): ?GoPass
    {
        return $this->goPass;
    }

    public function setGoPass(GoPass $goPass): static
    {
        $this->goPass = $goPass;

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

    public function getIssuedBy(): ?User
    {
        return $this->issuedBy;
    }

    public function setIssuedBy(?User $issuedBy): static
    {
        $this->issuedBy = $issuedBy;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function buildIssuedAt(): void
    {
        $this->issuedAt ??= new \DateTimeImmutable();
    }

    /**
     * Get the value of identifier
     */ 
    public function getIdentifier(): string|null
    {
        return $this->identifier;
    }

    /**
     * Set the value of identifier
     *
     * @return  self
     */ 
    public function setIdentifier(string|null $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get the value of uniqueReference
     */ 
    public function getUniqueReference(): string|null
    {
        return $this->uniqueReference;
    }

    /**
     * Set the value of uniqueReference
     *
     * @return  self
     */ 
    public function setUniqueReference(string|null $uniqueReference): static
    {
        $this->uniqueReference = $uniqueReference;

        return $this;
    }
}
