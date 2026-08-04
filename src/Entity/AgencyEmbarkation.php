<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use App\Dto\Agency\AddEmbarkationTicketsDto;
use App\Dto\Agency\CreateAgencyEmbarkationDto;
use App\Dto\Agency\UpdateAgencyEmbarkationStatusDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyEmbarkationRepository;
use App\State\Agency\AddEmbarkationTicketsProcessor;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyEmbarkationProcessor;
use App\State\Agency\DeclareEmbarkationProcessor;
use App\State\Agency\RemoveEmbarkationTicketProcessor;
use App\State\Agency\UpdateAgencyEmbarkationStatusProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyEmbarkationRepository::class)]
#[ORM\Table(name: '`agency_embarkation`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyEmbarkation',
    normalizationContext: ['groups' => ['agency_embarkation:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/embarkations',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/embarkations/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/embarkations',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyEmbarkationDto::class,
            processor: CreateAgencyEmbarkationProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/embarkations/{id}/tickets',
            security: 'is_granted("ROLE_PARTNER")',
            input: AddEmbarkationTicketsDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: AddEmbarkationTicketsProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/embarkations/{id}/tickets/{ticketId}',
            uriVariables: ['id', 'ticketId'],
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
            processor: RemoveEmbarkationTicketProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/embarkations/{id}/status',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyEmbarkationStatusDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyEmbarkationStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/embarkations/{id}/declare',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: DeclareEmbarkationProcessor::class,
            status: 201,
            normalizationContext: ['groups' => ['pass_declaration:get']],
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'label' => 'ipartial',
    'offer.id' => 'exact',
    'transport.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['departureDate', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'departureDate', 'departureTime'])]
class AgencyEmbarkation implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AE';

    public const string STATUS_PLANNED = 'PLANNED';
    public const string STATUS_BOARDING = 'BOARDING';
    public const string STATUS_DEPARTED = 'DEPARTED';
    public const string STATUS_DECLARED = 'DECLARED';
    public const string STATUS_CLOSED = 'CLOSED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AE_ID', length: 16)]
    #[Groups(['agency_embarkation:get', 'agency_ticket:get', 'pass_declaration:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AE_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_embarkation:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'AE_LABEL', length: 160)]
    #[Assert\NotBlank]
    #[Groups(['agency_embarkation:get'])]
    private ?string $label = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AE_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    #[Groups(['agency_embarkation:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AE_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_embarkation:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\Column(name: 'AE_DEPARTURE_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['agency_embarkation:get'])]
    private ?\DateTimeImmutable $departureDate = null;

    #[ORM\Column(name: 'AE_DEPARTURE_TIME', length: 5)]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/')]
    #[Groups(['agency_embarkation:get'])]
    private ?string $departureTime = null;

    #[ORM\Column(name: 'AE_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_embarkation:get'])]
    private string $status = self::STATUS_PLANNED;

    #[ORM\Column(name: 'AE_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_embarkation:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'AE_DEPARTED_AT', nullable: true)]
    #[Groups(['agency_embarkation:get'])]
    private ?\DateTimeImmutable $departedAt = null;

    #[ORM\Column(name: 'AE_DECLARED_AT', nullable: true)]
    #[Groups(['agency_embarkation:get'])]
    private ?\DateTimeImmutable $declaredAt = null;

    #[ORM\OneToOne(inversedBy: 'embarkation')]
    #[ORM\JoinColumn(name: 'AE_DECLARATION', nullable: true, referencedColumnName: 'PD_ID')]
    #[Groups(['agency_embarkation:get'])]
    private ?PassDeclaration $declaration = null;

    /** @var Collection<int, AgencyTicket> */
    #[ORM\OneToMany(mappedBy: 'embarkation', targetEntity: AgencyTicket::class)]
    #[Groups(['agency_embarkation:get'])]
    private Collection $tickets;

    #[ORM\Column(name: 'AE_CREATED_AT')]
    #[Groups(['agency_embarkation:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AE_UPDATED_AT', nullable: true)]
    #[Groups(['agency_embarkation:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
    }

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_PLANNED,
            self::STATUS_BOARDING,
            self::STATUS_DEPARTED,
            self::STATUS_DECLARED,
            self::STATUS_CLOSED,
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

    public function getOffer(): ?AgencyOffer
    {
        return $this->offer;
    }

    public function setOffer(?AgencyOffer $offer): static
    {
        $this->offer = $offer;

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

    public function getDepartureDate(): ?\DateTimeImmutable
    {
        return $this->departureDate;
    }

    public function setDepartureDate(\DateTimeImmutable $departureDate): static
    {
        $this->departureDate = $departureDate;

        return $this;
    }

    public function getDepartureTime(): ?string
    {
        return $this->departureTime;
    }

    public function setDepartureTime(string $departureTime): static
    {
        $this->departureTime = $departureTime;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getDepartedAt(): ?\DateTimeImmutable
    {
        return $this->departedAt;
    }

    public function setDepartedAt(?\DateTimeImmutable $departedAt): static
    {
        $this->departedAt = $departedAt;

        return $this;
    }

    public function getDeclaredAt(): ?\DateTimeImmutable
    {
        return $this->declaredAt;
    }

    public function setDeclaredAt(?\DateTimeImmutable $declaredAt): static
    {
        $this->declaredAt = $declaredAt;

        return $this;
    }

    public function getDeclaration(): ?PassDeclaration
    {
        return $this->declaration;
    }

    public function setDeclaration(?PassDeclaration $declaration): static
    {
        $this->declaration = $declaration;

        return $this;
    }

    /** @return Collection<int, AgencyTicket> */
    public function getTickets(): Collection
    {
        return $this->tickets;
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
