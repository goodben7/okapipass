<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
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
use App\Dto\Agency\CreateAgencyParcelDto;
use App\Dto\Agency\UpdateAgencyParcelDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyParcelRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CancelAgencyParcelProcessor;
use App\State\Agency\CreateAgencyParcelProcessor;
use App\State\Agency\DeliverAgencyParcelProcessor;
use App\State\Agency\UpdateAgencyParcelProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyParcelRepository::class)]
#[ORM\Table(name: '`agency_parcel`')]
#[ORM\UniqueConstraint(name: 'UNIQ_PARCEL_TRACKING', fields: ['agency', 'trackingCode'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyParcel',
    normalizationContext: ['groups' => ['agency_parcel:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/parcels',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/parcels/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/parcels',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyParcelDto::class,
            processor: CreateAgencyParcelProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/parcels/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyParcelDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyParcelProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/parcels/{id}/deliver',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: DeliverAgencyParcelProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/parcels/{id}/cancel',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: CancelAgencyParcelProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'trackingCode' => 'exact',
    'senderPhone' => 'exact',
    'recipientPhone' => 'exact',
    'offer.id' => 'exact',
    'transport.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['travelDate', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'travelDate', 'trackingCode'])]
class AgencyParcel implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'PL';

    public const string STATUS_BOOKED = 'BOOKED';
    public const string STATUS_IN_TRANSIT = 'IN_TRANSIT';
    public const string STATUS_DELIVERED = 'DELIVERED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PL_ID', length: 16)]
    #[Groups(['agency_parcel:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PL_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_parcel:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PL_OFFER', nullable: true, referencedColumnName: 'AO_ID')]
    #[Groups(['agency_parcel:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PL_TRANSPORT', nullable: true, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_parcel:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PL_EMBARKATION', nullable: true, referencedColumnName: 'AE_ID')]
    #[Groups(['agency_parcel:get'])]
    private ?AgencyEmbarkation $embarkation = null;

    #[ORM\Column(name: 'PL_SENDER_NAME', length: 160)]
    #[Groups(['agency_parcel:get'])]
    private ?string $senderName = null;

    #[ORM\Column(name: 'PL_SENDER_PHONE', length: 20)]
    #[Groups(['agency_parcel:get'])]
    private ?string $senderPhone = null;

    #[ORM\Column(name: 'PL_RECIPIENT_NAME', length: 160)]
    #[Groups(['agency_parcel:get'])]
    private ?string $recipientName = null;

    #[ORM\Column(name: 'PL_RECIPIENT_PHONE', length: 20)]
    #[Groups(['agency_parcel:get'])]
    private ?string $recipientPhone = null;

    #[ORM\Column(name: 'PL_WEIGHT_KG', nullable: true)]
    #[Groups(['agency_parcel:get'])]
    private ?float $weightKg = null;

    #[ORM\Column(name: 'PL_FEE')]
    #[Groups(['agency_parcel:get'])]
    private int $fee = 0;

    #[ORM\Column(name: 'PL_CURRENCY', length: 3)]
    #[Groups(['agency_parcel:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'PL_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_parcel:get'])]
    private string $status = self::STATUS_BOOKED;

    #[ORM\Column(name: 'PL_TRACKING_CODE', length: 32)]
    #[Groups(['agency_parcel:get'])]
    private ?string $trackingCode = null;

    #[ORM\Column(name: 'PL_TRAVEL_DATE', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['agency_parcel:get'])]
    private ?\DateTimeImmutable $travelDate = null;

    #[ORM\Column(name: 'PL_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_parcel:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'PL_CREATED_AT')]
    #[Groups(['agency_parcel:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'PL_UPDATED_AT', nullable: true)]
    #[Groups(['agency_parcel:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_BOOKED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
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

    public function getTransport(): ?AgencyTransport
    {
        return $this->transport;
    }

    public function setTransport(?AgencyTransport $transport): static
    {
        $this->transport = $transport;

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

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): static
    {
        $this->senderName = $senderName;

        return $this;
    }

    public function getSenderPhone(): ?string
    {
        return $this->senderPhone;
    }

    public function setSenderPhone(string $senderPhone): static
    {
        $this->senderPhone = $senderPhone;

        return $this;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): static
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getRecipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function setRecipientPhone(string $recipientPhone): static
    {
        $this->recipientPhone = $recipientPhone;

        return $this;
    }

    public function getWeightKg(): ?float
    {
        return $this->weightKg;
    }

    public function setWeightKg(?float $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function getFee(): int
    {
        return $this->fee;
    }

    public function setFee(int $fee): static
    {
        $this->fee = $fee;

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

    public function getTrackingCode(): ?string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(string $trackingCode): static
    {
        $this->trackingCode = $trackingCode;

        return $this;
    }

    public function getTravelDate(): ?\DateTimeImmutable
    {
        return $this->travelDate;
    }

    public function setTravelDate(?\DateTimeImmutable $travelDate): static
    {
        $this->travelDate = $travelDate;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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
