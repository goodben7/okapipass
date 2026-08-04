<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAgencyPaymentDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyPaymentRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyPaymentProcessor;
use App\State\Agency\RefundAgencyPaymentProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyPaymentRepository::class)]
#[ORM\Table(name: '`agency_payment`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_PAYMENT_REF', fields: ['reference'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyPayment',
    normalizationContext: ['groups' => ['agency_payment:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/payments',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/payments/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/payments',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyPaymentDto::class,
            processor: CreateAgencyPaymentProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/payments/{id}/refund',
            security: 'is_granted("ROLE_PARTNER")',
            input: false,
            deserialize: false,
            provider: AgencyScopedItemProvider::class,
            processor: RefundAgencyPaymentProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'reference' => 'exact',
    'status' => 'exact',
    'method' => 'exact',
    'ticket.id' => 'exact',
    'currency' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'amount', 'paidAt'])]
class AgencyPayment implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AP';

    public const string METHOD_CASH = 'CASH';
    public const string METHOD_MOBILE_MONEY = 'MOBILE_MONEY';
    public const string METHOD_CARD = 'CARD';

    public const string STATUS_PAID = 'PAID';
    public const string STATUS_REFUNDED = 'REFUNDED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AP_ID', length: 16)]
    #[Groups(['agency_payment:get', 'agency_ticket:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AP_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_payment:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AP_TICKET', nullable: false, referencedColumnName: 'AK_ID')]
    #[Groups(['agency_payment:get'])]
    private ?AgencyTicket $ticket = null;

    #[ORM\Column(name: 'AP_REFERENCE', length: 30)]
    #[Groups(['agency_payment:get'])]
    private ?string $reference = null;

    #[ORM\Column(name: 'AP_AMOUNT')]
    #[Groups(['agency_payment:get'])]
    private int $amount = 0;

    #[ORM\Column(name: 'AP_CURRENCY', length: 3)]
    #[Groups(['agency_payment:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'AP_METHOD', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getMethodsAsList'])]
    #[Groups(['agency_payment:get'])]
    private string $method = self::METHOD_CASH;

    #[ORM\Column(name: 'AP_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_payment:get'])]
    private string $status = self::STATUS_PAID;

    #[ORM\Column(name: 'AP_PAID_AT', nullable: true)]
    #[Groups(['agency_payment:get'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'AP_REFUNDED_AT', nullable: true)]
    #[Groups(['agency_payment:get'])]
    private ?\DateTimeImmutable $refundedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AP_COLLECTED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['agency_payment:get'])]
    private ?User $collectedBy = null;

    #[ORM\Column(name: 'AP_NOTES', length: 255, nullable: true)]
    #[Groups(['agency_payment:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'AP_CREATED_AT')]
    #[Groups(['agency_payment:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getMethodsAsList(): array
    {
        return [self::METHOD_CASH, self::METHOD_MOBILE_MONEY, self::METHOD_CARD];
    }

    public static function getStatusesAsList(): array
    {
        return [self::STATUS_PAID, self::STATUS_REFUNDED, self::STATUS_CANCELLED];
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

    public function getTicket(): ?AgencyTicket
    {
        return $this->ticket;
    }

    public function setTicket(?AgencyTicket $ticket): static
    {
        $this->ticket = $ticket;

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

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

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

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getRefundedAt(): ?\DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function setRefundedAt(?\DateTimeImmutable $refundedAt): static
    {
        $this->refundedAt = $refundedAt;

        return $this;
    }

    public function getCollectedBy(): ?User
    {
        return $this->collectedBy;
    }

    public function setCollectedBy(?User $collectedBy): static
    {
        $this->collectedBy = $collectedBy;

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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt ??= $now;
        $this->paidAt ??= $now;
    }
}
