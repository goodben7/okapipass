<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;

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
use App\Dto\Agency\RecordAgencyTicketBaggageDto;
use App\Dto\Agency\RecordBaggageResult;
use App\Dto\Agency\CreateAgencyTicketCancelRequestDto;
use App\Dto\Agency\RescheduleAgencyTicketDto;
use App\Dto\Agency\UpdateAgencyTicketSeatDto;
use App\Dto\Agency\UpdateAgencyTicketStatusDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyTicketRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyTicketCancelRequestProcessor;
use App\State\Agency\CreateAgencyTicketProcessor;
use App\State\Agency\RecordAgencyTicketBaggageProcessor;
use App\State\Agency\RefundAgencyTicketProcessor;
use App\State\Agency\RescheduleAgencyTicketProcessor;
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
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/tickets/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/tickets',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyTicketDto::class,
            output: AgencyTicketCreateResult::class,
            processor: CreateAgencyTicketProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/tickets/{id}/status',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyTicketStatusDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyTicketStatusProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/tickets/{id}/seat',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyTicketSeatDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyTicketSeatProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/tickets/{id}/refund',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: RefundAgencyTicketProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/tickets/{id}/baggage',
            security: AgencyPortalAccess::EXPRESSION,
            input: RecordAgencyTicketBaggageDto::class,
            output: RecordBaggageResult::class,
            normalizationContext: ['groups' => ['agency_baggage_excess:get', 'agency_ticket:get']],
            processor: RecordAgencyTicketBaggageProcessor::class,
            read: false,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/tickets/{id}/cancel-requests',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyTicketCancelRequestDto::class,
            output: \App\Entity\AgencyTicketCancelRequest::class,
            normalizationContext: ['groups' => ['agency_ticket_cancel_request:get']],
            processor: CreateAgencyTicketCancelRequestProcessor::class,
            read: false,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/tickets/{id}/reschedule',
            security: AgencyPortalAccess::EXPRESSION,
            input: RescheduleAgencyTicketDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: RescheduleAgencyTicketProcessor::class,
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
    public const string STATUS_NO_SHOW = 'NO_SHOW';

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

    #[ORM\OneToOne(inversedBy: 'ticket')]
    #[ORM\JoinColumn(name: 'AK_BOOKING_GROUP', nullable: true, referencedColumnName: 'BG_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?AgencyBookingGroup $bookingGroup = null;

    #[ORM\Column(name: 'AK_IS_GROUP')]
    #[Groups(['agency_ticket:get'])]
    private bool $isGroupTicket = false;

    #[ORM\Column(name: 'AK_GROUP_SEATS', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $groupSeats = null;

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

    #[ORM\Column(name: 'AK_DISCOUNT_AMOUNT', options: ['default' => 0])]
    #[Groups(['agency_ticket:get'])]
    private int $discountAmount = 0;

    #[ORM\Column(name: 'AK_PROMO_CODE', length: 40, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $promoCode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AK_LOYALTY_RULE', nullable: true, referencedColumnName: 'LR_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?LoyaltyRule $loyaltyRule = null;

    #[ORM\Column(name: 'AK_CURRENCY', length: 3)]
    #[Groups(['agency_ticket:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'AK_BAGGAGE_KG', nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?int $baggageKg = null;

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

    #[ORM\Column(name: 'AK_PASSENGER_DOB', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $passengerDateOfBirth = null;

    #[ORM\Column(name: 'AK_ESCORT_TICKET_ID', length: 16, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $escortTicketId = null;

    #[ORM\Column(name: 'AK_ESCORT_NAME', length: 120, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $escortName = null;

    #[ORM\Column(name: 'AK_QR_TOKEN', length: 64, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $qrToken = null;

    #[ORM\Column(name: 'AK_QR_TOKEN_EXPIRES_AT', nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $qrTokenExpiresAt = null;

    #[ORM\Column(name: 'AK_QR_TOKEN_USED_AT', nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $qrTokenUsedAt = null;

    #[ORM\Column(name: 'AK_LAST_BOARDED_AT', nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $lastBoardedAt = null;

    #[ORM\Column(name: 'AK_INSURANCE_OPTED', options: ['default' => false])]
    #[Groups(['agency_ticket:get'])]
    private bool $insuranceOpted = false;

    #[ORM\Column(name: 'AK_SHARE_TOKEN', length: 64, nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?string $shareToken = null;

    #[ORM\Column(name: 'AK_SHARE_TOKEN_EXPIRES_AT', nullable: true)]
    #[Groups(['agency_ticket:get'])]
    private ?\DateTimeImmutable $shareTokenExpiresAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AK_TRAVELER_PASS', nullable: true, referencedColumnName: 'TP_ID')]
    #[Groups(['agency_ticket:get'])]
    private ?TravelerPass $travelerPass = null;

    #[ORM\Column(name: 'AK_INSURANCE_FEE', options: ['default' => 0])]
    #[Groups(['agency_ticket:get'])]
    private int $insuranceFee = 0;

    #[ORM\Column(name: 'AK_RESCHEDULE_FEE', options: ['default' => 0])]
    #[Groups(['agency_ticket:get'])]
    private int $rescheduleFee = 0;

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
            self::STATUS_NO_SHOW,
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

    public function getBookingGroup(): ?AgencyBookingGroup
    {
        return $this->bookingGroup;
    }

    public function setBookingGroup(?AgencyBookingGroup $bookingGroup): static
    {
        $this->bookingGroup = $bookingGroup;
        if (null !== $bookingGroup && $bookingGroup->getTicket() !== $this) {
            $bookingGroup->setTicket($this);
        }

        return $this;
    }

    public function isGroupTicket(): bool
    {
        return $this->isGroupTicket;
    }

    public function setIsGroupTicket(bool $isGroupTicket): static
    {
        $this->isGroupTicket = $isGroupTicket;

        return $this;
    }

    public function getGroupSeats(): ?string
    {
        return $this->groupSeats;
    }

    public function setGroupSeats(?string $groupSeats): static
    {
        $this->groupSeats = $groupSeats;

        return $this;
    }

    /** @return list<string> */
    public function getGroupSeatList(): array
    {
        if (null === $this->groupSeats || '' === trim($this->groupSeats)) {
            return [];
        }

        $seats = array_map('trim', explode(',', $this->groupSeats));

        return array_values(array_filter($seats, static fn (string $s): bool => '' !== $s));
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

    public function getDiscountAmount(): int
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(int $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getPromoCode(): ?string
    {
        return $this->promoCode;
    }

    public function setPromoCode(?string $promoCode): static
    {
        $code = null !== $promoCode ? strtoupper(trim($promoCode)) : null;
        $this->promoCode = '' === $code ? null : $code;

        return $this;
    }

    public function getLoyaltyRule(): ?LoyaltyRule
    {
        return $this->loyaltyRule;
    }

    public function setLoyaltyRule(?LoyaltyRule $loyaltyRule): static
    {
        $this->loyaltyRule = $loyaltyRule;

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

    public function getPassengerDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->passengerDateOfBirth;
    }

    public function setPassengerDateOfBirth(?\DateTimeImmutable $passengerDateOfBirth): static
    {
        $this->passengerDateOfBirth = $passengerDateOfBirth;

        return $this;
    }

    public function getEscortTicketId(): ?string
    {
        return $this->escortTicketId;
    }

    public function setEscortTicketId(?string $escortTicketId): static
    {
        $this->escortTicketId = $escortTicketId;

        return $this;
    }

    public function getEscortName(): ?string
    {
        return $this->escortName;
    }

    public function setEscortName(?string $escortName): static
    {
        $this->escortName = $escortName;

        return $this;
    }

    public function getQrToken(): ?string
    {
        return $this->qrToken;
    }

    public function setQrToken(?string $qrToken): static
    {
        $this->qrToken = $qrToken;

        return $this;
    }

    public function getQrTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->qrTokenExpiresAt;
    }

    public function setQrTokenExpiresAt(?\DateTimeImmutable $qrTokenExpiresAt): static
    {
        $this->qrTokenExpiresAt = $qrTokenExpiresAt;

        return $this;
    }

    public function getQrTokenUsedAt(): ?\DateTimeImmutable
    {
        return $this->qrTokenUsedAt;
    }

    public function setQrTokenUsedAt(?\DateTimeImmutable $qrTokenUsedAt): static
    {
        $this->qrTokenUsedAt = $qrTokenUsedAt;

        return $this;
    }

    public function getLastBoardedAt(): ?\DateTimeImmutable
    {
        return $this->lastBoardedAt;
    }

    public function setLastBoardedAt(?\DateTimeImmutable $lastBoardedAt): static
    {
        $this->lastBoardedAt = $lastBoardedAt;

        return $this;
    }

    public function isInsuranceOpted(): bool
    {
        return $this->insuranceOpted;
    }

    public function setInsuranceOpted(bool $insuranceOpted): static
    {
        $this->insuranceOpted = $insuranceOpted;

        return $this;
    }

    public function getInsuranceFee(): int
    {
        return $this->insuranceFee;
    }

    public function setInsuranceFee(int $insuranceFee): static
    {
        $this->insuranceFee = $insuranceFee;

        return $this;
    }

    public function getShareToken(): ?string
    {
        return $this->shareToken;
    }

    public function setShareToken(?string $shareToken): static
    {
        $this->shareToken = $shareToken;

        return $this;
    }

    public function getShareTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->shareTokenExpiresAt;
    }

    public function setShareTokenExpiresAt(?\DateTimeImmutable $shareTokenExpiresAt): static
    {
        $this->shareTokenExpiresAt = $shareTokenExpiresAt;

        return $this;
    }

    public function getTravelerPass(): ?TravelerPass
    {
        return $this->travelerPass;
    }

    public function setTravelerPass(?TravelerPass $travelerPass): static
    {
        $this->travelerPass = $travelerPass;

        return $this;
    }

    public function getRescheduleFee(): int
    {
        return $this->rescheduleFee;
    }

    public function setRescheduleFee(int $rescheduleFee): static
    {
        $this->rescheduleFee = $rescheduleFee;

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

    public function isNoShow(): bool
    {
        return self::STATUS_NO_SHOW === $this->status;
    }

    public function freesSeat(): bool
    {
        return self::STATUS_CANCELLED === $this->status || self::STATUS_NO_SHOW === $this->status;
    }

    public function getBaggageKg(): ?int
    {
        return $this->baggageKg;
    }

    public function setBaggageKg(?int $baggageKg): static
    {
        $this->baggageKg = $baggageKg;

        return $this;
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
