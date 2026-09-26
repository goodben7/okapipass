<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
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
use App\Dto\Traveler\ConvertTravelerPreorderDto;
use App\Dto\Traveler\CreateTravelerPreorderDto;
use App\Model\RessourceInterface;
use App\Repository\TravelerPreorderRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CancelTravelerPreorderProcessor;
use App\State\Agency\ConfirmTravelerPreorderProcessor;
use App\State\Traveler\ConvertTravelerPreorderProcessor;
use App\State\Traveler\CreateTravelerPreorderProcessor;
use App\Provider\Traveler\TravelerPreorderItemProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TravelerPreorderRepository::class)]
#[ORM\Table(name: '`traveler_preorder`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'TravelerPreorder',
    normalizationContext: ['groups' => ['traveler_preorder:get']],
    operations: [
        new Post(
            uriTemplate: '/traveler/preorders',
            security: 'is_granted("ROLE_TRAVELER")',
            input: CreateTravelerPreorderDto::class,
            processor: CreateTravelerPreorderProcessor::class,
            status: 201,
        ),
        new Post(
            uriTemplate: '/traveler/preorders/{id}/convert',
            security: 'is_granted("ROLE_TRAVELER")',
            input: ConvertTravelerPreorderDto::class,
            provider: TravelerPreorderItemProvider::class,
            processor: ConvertTravelerPreorderProcessor::class,
            status: 200,
        ),
        new GetCollection(
            uriTemplate: '/agency/preorders',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/preorders/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/agency/preorders/{id}/confirm',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: ConfirmTravelerPreorderProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/preorders/{id}/cancel',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: CancelTravelerPreorderProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'offer' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'holdUntil', 'travelDatePreferred'])]
class TravelerPreorder implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'PO';

    public const string STATUS_HELD = 'HELD';
    public const string STATUS_CONVERTED = 'CONVERTED';
    public const string STATUS_EXPIRED = 'EXPIRED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PO_ID', length: 16)]
    #[Groups(['traveler_preorder:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PO_USER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['traveler_preorder:get'])]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PO_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['traveler_preorder:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PO_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    #[Groups(['traveler_preorder:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'PO_STATUS', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['traveler_preorder:get'])]
    private string $status = self::STATUS_HELD;

    #[ORM\Column(name: 'PO_PASSENGER_NAME', length: 160)]
    #[Groups(['traveler_preorder:get'])]
    private ?string $passengerName = null;

    #[ORM\Column(name: 'PO_PASSENGER_ID', length: 80)]
    #[Groups(['traveler_preorder:get'])]
    private ?string $passengerId = null;

    #[ORM\Column(name: 'PO_PASSENGER_PHONE', length: 20)]
    #[Groups(['traveler_preorder:get'])]
    private ?string $passengerPhone = null;

    #[ORM\Column(name: 'PO_SEAT_NUMBER', length: 20, nullable: true)]
    #[Groups(['traveler_preorder:get'])]
    private ?string $seatNumber = null;

    #[ORM\Column(name: 'PO_TRAVEL_DATE_PREFERRED', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['traveler_preorder:get'])]
    private ?\DateTimeImmutable $travelDatePreferred = null;

    #[ORM\Column(name: 'PO_HOLD_UNTIL')]
    #[Groups(['traveler_preorder:get'])]
    private ?\DateTimeImmutable $holdUntil = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PO_BOOKING', nullable: true, referencedColumnName: 'AB_ID')]
    #[Groups(['traveler_preorder:get'])]
    private ?AgencyBooking $booking = null;

    #[ORM\Column(name: 'PO_CREATED_AT')]
    #[Groups(['traveler_preorder:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_HELD,
            self::STATUS_CONVERTED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function setSeatNumber(?string $seatNumber): static
    {
        $this->seatNumber = $seatNumber;

        return $this;
    }

    public function getTravelDatePreferred(): ?\DateTimeImmutable
    {
        return $this->travelDatePreferred;
    }

    public function setTravelDatePreferred(?\DateTimeImmutable $travelDatePreferred): static
    {
        $this->travelDatePreferred = $travelDatePreferred;

        return $this;
    }

    public function getHoldUntil(): ?\DateTimeImmutable
    {
        return $this->holdUntil;
    }

    public function setHoldUntil(\DateTimeImmutable $holdUntil): static
    {
        $this->holdUntil = $holdUntil;

        return $this;
    }

    public function getBooking(): ?AgencyBooking
    {
        return $this->booking;
    }

    public function setBooking(?AgencyBooking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
    }
}
