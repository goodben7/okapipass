<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
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
use App\Dto\Agency\CreateAgencyOfferDto;
use App\Dto\Agency\UpdateAgencyOfferDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyOfferRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyOfferProcessor;
use App\State\Agency\DeleteAgencyOfferProcessor;
use App\State\Agency\UpdateAgencyOfferProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyOfferRepository::class)]
#[ORM\Table(name: '`agency_offer`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyOffer',
    normalizationContext: ['groups' => ['agency_offer:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/offers',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/offers/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/offers',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyOfferDto::class,
            processor: CreateAgencyOfferProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/offers/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyOfferDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyOfferProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/offers/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyOfferProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'label' => 'ipartial',
    'origin' => 'ipartial',
    'destination' => 'ipartial',
    'transport.id' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'label', 'ticketPrice', 'departureTime'])]
class AgencyOffer implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AO';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AO_ID', length: 16)]
    #[Groups(['agency_offer:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AO_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_offer:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AO_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_offer:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\Column(name: 'AO_LABEL', length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['agency_offer:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'AO_ORIGIN', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agency_offer:get'])]
    private ?string $origin = null;

    #[ORM\Column(name: 'AO_DESTINATION', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agency_offer:get'])]
    private ?string $destination = null;

    /** Ticket price in agency currency — Pass ONT not included (spec §6.3). */
    #[ORM\Column(name: 'AO_TICKET_PRICE')]
    #[Assert\PositiveOrZero]
    #[Groups(['agency_offer:get'])]
    private ?int $ticketPrice = null;

    #[ORM\Column(name: 'AO_CURRENCY', length: 3)]
    #[Assert\Currency]
    #[Groups(['agency_offer:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'AO_DEPARTURE_TIME', length: 5)]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/')]
    #[Groups(['agency_offer:get'])]
    private ?string $departureTime = null;

    #[ORM\Column(name: 'AO_DURATION_MINUTES')]
    #[Assert\Positive]
    #[Groups(['agency_offer:get'])]
    private ?int $durationMinutes = null;

    #[ORM\Column(name: 'AO_ACTIVE')]
    #[Groups(['agency_offer:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'AO_CREATED_AT')]
    #[Groups(['agency_offer:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AO_UPDATED_AT', nullable: true)]
    #[Groups(['agency_offer:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getTicketPrice(): ?int
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(int $ticketPrice): static
    {
        $this->ticketPrice = $ticketPrice;

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

    public function getDepartureTime(): ?string
    {
        return $this->departureTime;
    }

    public function setDepartureTime(string $departureTime): static
    {
        $this->departureTime = $departureTime;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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
