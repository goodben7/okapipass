<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\AgencyTicketCreateResult;
use App\Dto\Agency\CreateAgencyTicketDto;
use App\Dto\Agency\UpdateAgencyTicketSeatDto;
use App\Dto\Agency\UpdateAgencyTicketStatusDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyTicketRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyTicketProcessor;
use App\State\Agency\RefundAgencyTicketProcessor;
use App\State\Agency\UpdateAgencyTicketSeatProcessor;
use App\State\Agency\UpdateAgencyTicketStatusProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyTicketRepository::class)]
#[ORM\Table(name: '`agency_ticket`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_TICKET_REFERENCE', fields: ['reference'])]
#[ORM\Index(name: 'IDX_AGENCY_TICKET_OCCUPANCY', columns: ['AK_OFFER', 'AK_TRAVEL_DATE', 'AK_STATUS'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyTicket',
    normalizationContext: ['groups' => ['agency_ticket:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/tickets',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/tickets/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/tickets',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyTicketDto::class,
            output: AgencyTicketCreateResult::class,
            processor: CreateAgencyTicketProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/tickets/{id}/status',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyTicketStatusDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyTicketStatusProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/tickets/{id}/seat',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyTicketSeatDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyTicketSeatProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/tickets/{id}/refund',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: RefundAgencyTicketProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'reference' => 'exact',
    'status' => 'exact',
    'passengerName' => 'ipartial',
    'passengerPhone' => 'exact',
    'seatNumber' => 'exact',
    'offer.id' => 'exact',
    'okapiPassRef' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['hasExistingPass'])]
#[ApiFilter(DateFilter::class, properties: ['travelDate', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'travelDate', 'reference'])]
class AgencyTicket implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AK';

    public const string STATUS_ISSUED = 'ISSUED';
    public const string STATUS_BOARDED = 'BOARDED';
    public const string STATUS_CANCELLED = 'CANCELLED';
    public const string STATUS_USED = 'USED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AK_ID', length: 16)]
    #[Groups(['agency_ticket:get', 'agency_booking:get', 'agency_payment:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AK_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'AK_REFERENCE', length: 20)]
    #[Groups(['agency_ticket:get', 'agency_booking:get', 'agency_payment:get'])]
    private ?string $reference = null;

    #[ORM\OneToOne(inversedBy: 'ticket')]
    #[ORM\JoinColumn(name: 'AK_BOOKING', nullable: true, referencedColumnName: 'AB_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?AgencyBooking $booking = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AK_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'AK_PASSENGER_NAME', length: 120)]
    #[Groups(['agency_ticket:get'])]
    private ?string $passengerName = null;

    #[ORM\Column(name: 'AK_PASSENGER_ID', length: 60)]
    #[Groups(['agency_ticket:get'])]
    private ?string $passengerId = null;

    #[ORM\Column(name: 'AK_PASSENGER_PHONE', length: 20)]
    #[Groups(['agency_ticket:get'])]
    private ?string $passengerPhone = null;

    #[ORM\Column(name: 'AK_SEAT_NUMBER', length: 10)]
    #[Groups(['agency_ticket:get'])]
    private ?string $seatNumber = null;

    #[ORM\Column(name: 'AK_TRAVEL_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $travelDate = null;

    #[ORM\Column(name: 'AK_TICKET_PRICE')]
    #[Groups(['agency_ticket:get'])]
    private int $ticketPrice = 0;

    #[ORM\Column(name: 'AK_PASS_PRICE')]
    #[Groups(['agency_ticket:get'])]
    private int $passPrice = 0;

    #[ORM\Column(name: 'AK_CURRENCY', length: 3)]
    #[Groups(['agency_ticket:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'AK_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_ticket:get', 'agency_booking:get'])]
    private string $status = self::STATUS_ISSUED;

    #[ORM\Column(name: 'AK_OKAPI_PASS_REF', length: 40, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $okapiPassRef = null;

    #[ORM\Column(name: 'AK_HAS_EXISTING_PASS')]
    private bool $hasExistingPass = false;

    #[ORM\Column(name: 'AK_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'AK_QR_PAYLOAD', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $qrPayload = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(name: 'AK_EMBARKATION', nullable: true, referencedColumnName: 'AE_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?AgencyEmbarkation $embarkation = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(name: 'AK_DECLARATION', nullable: true, referencedColumnName: 'PD_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?PassDeclaration $declaration = null;

    #[ORM\Column(name: 'AK_CREATED_AT')]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AK_UPDATED_AT', nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ISSUED,
            self::STATUS_BOARDED,
            self::STATUS_CANCELLED,
            self::STATUS_USED,
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getBooking(): ?AgencyBooking
    {
        return $this->booking;
    }

    public function setBooking(?AgencyBooking $booking): static
    {
        $this->booking = $booking;
        if (null !== $booking && $booking->getTicket() !== $this) {
            $booking->setTicket($this);
        }

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

    public function getPassengerName(): ?string
    {
        return $this->passengerName;
    }

    public function setPassengerName(string $passengerName): static
    {
        $this->passengerName = $passengerName;

        return $this;
    }

    public function getPassengerId(): ?string
    {
        return $this->passengerId;
    }

    public function setPassengerId(string $passengerId): static
    {
        $this->passengerId = $passengerId;

        return $this;
    }

    public function getPassengerPhone(): ?string
    {
        return $this->passengerPhone;
    }

    public function setPassengerPhone(string $passengerPhone): static
    {
        $this->passengerPhone = $passengerPhone;

        return $this;
    }

    public function getSeatNumber(): ?string
    {
        return $this->seatNumber;
    }

    public function setSeatNumber(string $seatNumber): static
    {
        $this->seatNumber = strtoupper(trim($seatNumber));

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

    public function getTicketPrice(): int
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(int $ticketPrice): static
    {
        $this->ticketPrice = $ticketPrice;

        return $this;
    }

    public function getPassPrice(): int
    {
        return $this->passPrice;
    }

    public function setPassPrice(int $passPrice): static
    {
        $this->passPrice = $passPrice;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOkapiPassRef(): ?string
    {
        return $this->okapiPassRef;
    }

    public function setOkapiPassRef(?string $okapiPassRef): static
    {
        $ref = null !== $okapiPassRef ? strtoupper(trim($okapiPassRef)) : null;
        $this->okapiPassRef = '' === $ref ? null : $ref;

        return $this;
    }

    public function hasExistingPass(): bool
    {
        return $this->hasExistingPass;
    }

    #[Groups(['agency_ticket:get'])]
    #[SerializedName('hasExistingPass')]
    public function getHasExistingPass(): bool
    {
        return $this->hasExistingPass;
    }

    public function setHasExistingPass(bool $hasExistingPass): static
    {
        $this->hasExistingPass = $hasExistingPass;

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

    public function getQrPayload(): ?string
    {
        return $this->qrPayload;
    }

    public function setQrPayload(?string $qrPayload): static
    {
        $this->qrPayload = $qrPayload;

        return $this;
    }

    public function getEmbarkation(): ?AgencyEmbarkation
    {
        return $this->embarkation;
    }

    public function setEmbarkation(?AgencyEmbarkation $embarkation): static
    {
        $this->embarkation = $embarkation;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCancelled(): bool
    {
        return self::STATUS_CANCELLED === $this->status;
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
