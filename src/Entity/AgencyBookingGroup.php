<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\AgencyBookingGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyBookingGroupRepository::class)]
#[ORM\Table(name: '`agency_booking_group`')]
#[ORM\Index(name: 'IDX_AGENCY_BOOKING_GROUP_EXPIRY', columns: ['BG_STATUS', 'BG_EXPIRES_AT'])]
#[ORM\HasLifecycleCallbacks]
class AgencyBookingGroup implements RessourceInterface
{
    public const string ID_PREFIX = 'BG';

    public const string STATUS_PENDING = AgencyBooking::STATUS_PENDING;
    public const string STATUS_CONFIRMED = AgencyBooking::STATUS_CONFIRMED;
    public const string STATUS_CANCELLED = AgencyBooking::STATUS_CANCELLED;
    public const string STATUS_COMPLETED = AgencyBooking::STATUS_COMPLETED;

    public const string CHANNEL_ONLINE = AgencyBooking::CHANNEL_ONLINE;

    public const string PAYMENT_STATUS_UNPAID = AgencyBooking::PAYMENT_STATUS_UNPAID;
    public const string PAYMENT_STATUS_PENDING = AgencyBooking::PAYMENT_STATUS_PENDING;
    public const string PAYMENT_STATUS_PAID = AgencyBooking::PAYMENT_STATUS_PAID;
    public const string PAYMENT_STATUS_FAILED = AgencyBooking::PAYMENT_STATUS_FAILED;
    public const string PAYMENT_STATUS_REFUNDED = AgencyBooking::PAYMENT_STATUS_REFUNDED;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'BG_ID', length: 16)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'BG_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'BG_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'BG_GROUP_NAME', length: 120)]
    #[Assert\NotBlank]
    private ?string $groupName = null;

    #[ORM\Column(name: 'BG_CONTACT_PHONE', length: 20, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(name: 'BG_TRAVEL_DATE', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $travelDate = null;

    #[ORM\Column(name: 'BG_STATUS', length: 12)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'BG_CHANNEL', length: 10)]
    private string $channel = self::CHANNEL_ONLINE;

    #[ORM\Column(name: 'BG_EXPIRES_AT', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'BG_PUBLIC_TOKEN', length: 64, unique: true)]
    private ?string $publicToken = null;

    #[ORM\Column(name: 'BG_PAYMENT_STATUS', length: 12)]
    private string $paymentStatus = self::PAYMENT_STATUS_UNPAID;

    #[ORM\Column(name: 'BG_CREATED_AT')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'BG_UPDATED_AT', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, AgencyBooking> */
    #[ORM\OneToMany(mappedBy: 'bookingGroup', targetEntity: AgencyBooking::class)]
    private Collection $bookings;

    #[ORM\OneToOne(mappedBy: 'bookingGroup', targetEntity: AgencyTicket::class)]
    private ?AgencyTicket $ticket = null;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
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

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): static
    {
        $this->groupName = $groupName;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

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

    public function setPublicToken(string $publicToken): static
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, AgencyBooking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(AgencyBooking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setBookingGroup($this);
        }

        return $this;
    }

    public function isCancelled(): bool
    {
        return self::STATUS_CANCELLED === $this->status;
    }

    public function syncChildBookingStates(): void
    {
        foreach ($this->bookings as $booking) {
            $booking->setStatus($this->status);
            $booking->setPaymentStatus($this->paymentStatus);
            $booking->setExpiresAt($this->expiresAt);
        }
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
