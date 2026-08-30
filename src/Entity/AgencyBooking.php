<?php

namespace App\Entity;

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
use App\Dto\Agency\AgencyBookingCreateResult;
use App\Dto\Agency\CreateAgencyBookingDto;
use App\Dto\Agency\UpdateAgencyBookingDto;
use App\Dto\Agency\UpdateAgencyBookingStatusDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyBookingRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyBookingProcessor;
use App\State\Agency\IssueTicketFromBookingProcessor;
use App\State\Agency\UpdateAgencyBookingProcessor;
use App\State\Agency\UpdateAgencyBookingStatusProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyBookingRepository::class)]
#[ORM\Table(name: '`agency_booking`')]
#[ORM\Index(name: 'IDX_AGENCY_BOOKING_OCCUPANCY', columns: ['AB_OFFER', 'AB_TRAVEL_DATE', 'AB_STATUS'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyBooking',
    normalizationContext: ['groups' => ['agency_booking:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/bookings',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/bookings/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/bookings',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyBookingDto::class,
            output: AgencyBookingCreateResult::class,
            processor: CreateAgencyBookingProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/bookings/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyBookingDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyBookingProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/bookings/{id}/status',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyBookingStatusDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyBookingStatusProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/bookings/{id}/issue-ticket',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            read: true,
            provider: AgencyScopedItemProvider::class,
            processor: IssueTicketFromBookingProcessor::class,
            deserialize: false,
            status: 201,
            output: AgencyTicket::class,
            normalizationContext: ['groups' => ['agency_ticket:get']],
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'passengerName' => 'ipartial',
    'passengerPhone' => 'exact',
    'seatNumber' => 'exact',
    'offer.id' => 'exact',
    'okapiPassRef' => 'exact',
    'channel' => 'exact',
    'paymentStatus' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['travelDate', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'travelDate', 'passengerName'])]
class AgencyBooking implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AB';

    public const string STATUS_PENDING = 'PENDING';
    public const string STATUS_CONFIRMED = 'CONFIRMED';
    public const string STATUS_CANCELLED = 'CANCELLED';
    public const string STATUS_COMPLETED = 'COMPLETED';

    public const string CHANNEL_DESK = 'DESK';
    public const string CHANNEL_ONLINE = 'ONLINE';

    public const string PAYMENT_STATUS_UNPAID = 'UNPAID';
    public const string PAYMENT_STATUS_PENDING = 'PENDING';
    public const string PAYMENT_STATUS_PAID = 'PAID';
    public const string PAYMENT_STATUS_FAILED = 'FAILED';
    public const string PAYMENT_STATUS_REFUNDED = 'REFUNDED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AB_ID', length: 16)]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AB_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_booking:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AB_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'AB_PASSENGER_NAME', length: 120)]
    #[Assert\NotBlank]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?string $passengerName = null;

    #[ORM\Column(name: 'AB_PASSENGER_ID', length: 60)]
    #[Assert\NotBlank]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?string $passengerId = null;

    #[ORM\Column(name: 'AB_PASSENGER_PHONE', length: 20)]
    #[Assert\NotBlank]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?string $passengerPhone = null;

    #[ORM\Column(name: 'AB_SEAT_NUMBER', length: 10)]
    #[Assert\NotBlank]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?string $seatNumber = null;

    #[ORM\Column(name: 'AB_TRAVEL_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?\DateTimeImmutable $travelDate = null;

    #[ORM\Column(name: 'AB_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_booking:get'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'AB_CHANNEL', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getChannelsAsList'])]
    #[Groups(['agency_booking:get'])]
    private string $channel = self::CHANNEL_DESK;

    #[ORM\Column(name: 'AB_EXPIRES_AT', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['agency_booking:get'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'AB_PUBLIC_TOKEN', length: 64, nullable: true, unique: true)]
    private ?string $publicToken = null;

    #[ORM\Column(name: 'AB_PAYMENT_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getPaymentStatusesAsList'])]
    #[Groups(['agency_booking:get'])]
    private string $paymentStatus = self::PAYMENT_STATUS_UNPAID;

    #[ORM\Column(name: 'AB_OKAPI_PASS_REF', length: 40, nullable: true)]
    #[Groups(['agency_booking:get', 'agency_ticket:get'])]
    private ?string $okapiPassRef = null;

    #[ORM\Column(name: 'AB_CREATED_AT')]
    #[Groups(['agency_booking:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AB_UPDATED_AT', nullable: true)]
    #[Groups(['agency_booking:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: AgencyTicket::class)]
    #[Groups(['agency_booking:get'])]
    private ?AgencyTicket $ticket = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED,
        ];
    }

    /** @return list<string> */
    public static function getChannelsAsList(): array
    {
        return [self::CHANNEL_DESK, self::CHANNEL_ONLINE];
    }

    /** @return list<string> */
    public static function getPaymentStatusesAsList(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID,
            self::PAYMENT_STATUS_PENDING,
            self::PAYMENT_STATUS_PAID,
            self::PAYMENT_STATUS_FAILED,
            self::PAYMENT_STATUS_REFUNDED,
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt < new \DateTimeImmutable('now');
    }

    public function getPublicToken(): ?string
    {
        return $this->publicToken;
    }

    public function setPublicToken(?string $publicToken): static
    {
        $this->publicToken = $publicToken;

        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getTicket(): ?AgencyTicket
    {
        return $this->ticket;
    }

    public function setTicket(?AgencyTicket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
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
