<?php

namespace App\Entity;

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
use App\Dto\Agency\CreateAgencyRentalContractDto;
use App\Dto\Agency\CreateAgencyRentalPaymentDto;
use App\Dto\Agency\UpdateAgencyRentalContractDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyRentalContractRepository;
use App\State\Agency\ActivateAgencyRentalContractProcessor;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CancelAgencyRentalContractProcessor;
use App\State\Agency\CheckAgencyRentalPaymentProcessor;
use App\State\Agency\ConfirmAgencyRentalContractProcessor;
use App\State\Agency\CreateAgencyRentalContractProcessor;
use App\State\Agency\CreateAgencyRentalPaymentProcessor;
use App\State\Agency\ReturnAgencyRentalContractProcessor;
use App\State\Agency\UpdateAgencyRentalContractProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyRentalContractRepository::class)]
#[ORM\Table(name: '`agency_rental_contract`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyRentalContract',
    normalizationContext: ['groups' => ['agency_rental:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/rental-contracts',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/rental-contracts/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyRentalContractDto::class,
            processor: CreateAgencyRentalContractProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/rental-contracts/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyRentalContractDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyRentalContractProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts/{id}/confirm',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ConfirmAgencyRentalContractProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts/{id}/activate',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ActivateAgencyRentalContractProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts/{id}/return',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ReturnAgencyRentalContractProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts/{id}/cancel',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: CancelAgencyRentalContractProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts/{id}/payments',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyRentalPaymentDto::class,
            output: AgencyPayment::class,
            normalizationContext: ['groups' => ['agency_payment:get']],
            provider: AgencyScopedItemProvider::class,
            processor: CreateAgencyRentalPaymentProcessor::class,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/rental-contracts/{id}/payments/check-status',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            validate: false,
            output: AgencyPayment::class,
            normalizationContext: ['groups' => ['agency_payment:get']],
            provider: AgencyScopedItemProvider::class,
            processor: CheckAgencyRentalPaymentProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'transport.id' => 'exact',
    'clientName' => 'ipartial',
])]
class AgencyRentalContract implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'RC';

    public const string STATUS_DRAFT = 'DRAFT';
    public const string STATUS_CONFIRMED = 'CONFIRMED';
    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_RETURNED = 'RETURNED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'RC_ID', length: 16)]
    #[Groups(['agency_rental:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'RC_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_rental:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'RC_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_rental:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'RC_DRIVER', nullable: true, referencedColumnName: 'AD_ID')]
    #[Groups(['agency_rental:get'])]
    private ?AgencyDriver $driver = null;

    #[ORM\Column(name: 'RC_CLIENT_NAME', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agency_rental:get'])]
    private ?string $clientName = null;

    #[ORM\Column(name: 'RC_CLIENT_PHONE', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Groups(['agency_rental:get'])]
    private ?string $clientPhone = null;

    #[ORM\Column(name: 'RC_CLIENT_COMPANY', length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Groups(['agency_rental:get'])]
    private ?string $clientCompany = null;

    #[ORM\Column(name: 'RC_START_AT')]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(name: 'RC_END_AT')]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(name: 'RC_PICKUP_LOCATION', length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    #[Groups(['agency_rental:get'])]
    private ?string $pickupLocation = null;

    #[ORM\Column(name: 'RC_DROPOFF_LOCATION', length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    #[Groups(['agency_rental:get'])]
    private ?string $dropoffLocation = null;

    #[ORM\Column(name: 'RC_DAILY_RATE')]
    #[Assert\Positive]
    #[Groups(['agency_rental:get'])]
    private ?int $dailyRate = null;

    #[ORM\Column(name: 'RC_TOTAL_AMOUNT')]
    #[Assert\PositiveOrZero]
    #[Groups(['agency_rental:get'])]
    private ?int $totalAmount = null;

    #[ORM\Column(name: 'RC_DEPOSIT_AMOUNT', nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['agency_rental:get'])]
    private ?int $depositAmount = null;

    #[ORM\Column(name: 'RC_CURRENCY', length: 3)]
    #[Assert\Length(exactly: 3)]
    #[Groups(['agency_rental:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'RC_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_rental:get'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'RC_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_rental:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'RC_CONFIRMED_AT', nullable: true)]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(name: 'RC_ACTIVATED_AT', nullable: true)]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $activatedAt = null;

    #[ORM\Column(name: 'RC_RETURNED_AT', nullable: true)]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\Column(name: 'RC_CREATED_AT')]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'RC_UPDATED_AT', nullable: true)]
    #[Groups(['agency_rental:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CONFIRMED,
            self::STATUS_ACTIVE,
            self::STATUS_RETURNED,
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

    public function getTransport(): ?AgencyTransport
    {
        return $this->transport;
    }

    public function setTransport(?AgencyTransport $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function getDriver(): ?AgencyDriver
    {
        return $this->driver;
    }

    public function setDriver(?AgencyDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getClientPhone(): ?string
    {
        return $this->clientPhone;
    }

    public function setClientPhone(string $clientPhone): static
    {
        $this->clientPhone = $clientPhone;

        return $this;
    }

    public function getClientCompany(): ?string
    {
        return $this->clientCompany;
    }

    public function setClientCompany(?string $clientCompany): static
    {
        $this->clientCompany = $clientCompany;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getPickupLocation(): ?string
    {
        return $this->pickupLocation;
    }

    public function setPickupLocation(?string $pickupLocation): static
    {
        $this->pickupLocation = $pickupLocation;

        return $this;
    }

    public function getDropoffLocation(): ?string
    {
        return $this->dropoffLocation;
    }

    public function setDropoffLocation(?string $dropoffLocation): static
    {
        $this->dropoffLocation = $dropoffLocation;

        return $this;
    }

    public function getDailyRate(): ?int
    {
        return $this->dailyRate;
    }

    public function setDailyRate(int $dailyRate): static
    {
        $this->dailyRate = $dailyRate;

        return $this;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getDepositAmount(): ?int
    {
        return $this->depositAmount;
    }

    public function setDepositAmount(?int $depositAmount): static
    {
        $this->depositAmount = $depositAmount;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getActivatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(?\DateTimeImmutable $activatedAt): static
    {
        $this->activatedAt = $activatedAt;

        return $this;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTimeImmutable $returnedAt): static
    {
        $this->returnedAt = $returnedAt;

        return $this;
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
