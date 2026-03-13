<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Dto\CreatePaymentDto;
use App\Dto\FlexpayWebhookDto;
use App\Model\RessourceInterface;
use App\Repository\PaymentRepository;
use App\State\CreatePaymentProcessor;
use App\State\FlexpayCheckStatusProcessor;
use App\State\FlexpayWebhookProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)] 
#[ORM\Table(name: '`payment`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_PAYMENT_REFERENCE', fields: ['reference'])]
#[ApiResource(
    normalizationContext: ['groups' => 'payment:get'],
    operations: [
        new Get(
            security: 'is_granted("ROLE_PAYMENT_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            security: 'is_granted("ROLE_PAYMENT_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_PAYMENT_CREATE")',
            input: CreatePaymentDto::class,
            processor: CreatePaymentProcessor::class,
        ),
        new Post(
            uriTemplate: '/payments/webhook/flexpay',
            input: FlexpayWebhookDto::class,
            processor: FlexpayWebhookProcessor::class,
            read: false,
            denormalizationContext: ['allow_extra_attributes' => true],
            status: 200
        ),
        new Post(
            uriTemplate: '/payments/{id}/check-status/flexpay',
            //security: 'is_granted("ROLE_PAYMENT_DETAILS")',
            input: false,
            processor: FlexpayCheckStatusProcessor::class,
            deserialize: false,
            status: 200
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'reference' => 'exact',
    'ticket.id' => 'exact',
    'currency' => 'exact',
    'method' => 'exact',
    'status' => 'exact',
    'paidBy.id' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'paidAt', 'amount'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'paidAt'])]
class Payment implements RessourceInterface
{
    public const string EVENT_PAYMENT_CREATED = "payment.created";

    public const string ID_PREFIX = 'PA';

    public const string STATUS_PENDING = 'PENDING';
    public const string STATUS_PAID = 'PAID';
    public const string STATUS_FAILED = 'FAILED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    public const string PROVIDER_FLEXPAY = 'FLEXPAY';

    public const string METHOD_MOBILE_MONEY = 'MOBILE_MONEY';
    public const string METHOD_CARD = 'CARD';
    public const string METHOD_CASH = 'CASH';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PA_ID', length: 16)]
    #[Groups(['payment:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'PA_REFERENCE', length: 80)]
    #[Assert\NotBlank]
    #[Groups(['payment:get'])]
    private ?string $reference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PA_TICKET', nullable: false, referencedColumnName: 'TI_ID')]
    #[Assert\NotNull]
    #[Groups(['payment:get'])]
    private ?Ticket $ticket = null;

    #[ORM\Column(name: 'PA_AMOUNT', type: Types::DECIMAL, precision: 17, scale: 2)]
    #[Assert\NotNull]
    #[Groups(['payment:get'])]
    private ?string $amount = null;

    #[ORM\Column(name: 'PA_CURRENCY', length: 3)]
    #[Assert\NotBlank]
    #[Assert\Currency]
    #[Groups(['payment:get'])]
    private ?string $currency = null;

    #[ORM\Column(name: 'PA_METHOD', length: 30)]
    #[Assert\NotBlank]

    #[Groups(['payment:get'])]
    private ?string $method = null;

    #[ORM\Column(name: 'PA_STATUS', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['payment:get'])]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'PA_PROVIDER_TRANSACTION_ID', length: 120, nullable: true)]
    #[Groups(['payment:get'])]
    private ?string $providerTransactionId = null;

    #[ORM\Column(name: 'PA_PROVIDER', length: 30, nullable: true)]
    #[Groups(['payment:get'])]
    private ?string $provider = null;

    #[ORM\Column(name: 'PA_PROVIDER_RESPONSE', type: Types::JSON, nullable: true)]
    #[Groups(['payment:get'])]
    private ?array $providerResponse = null;

    #[ORM\Column(type: Types::JSON, nullable: true, name:'PA_PROVIDER_WEBHOOK')]
    #[Groups(['payment:get'])]
    private ?array $providerWebhook = null;

    #[ORM\Column(name: 'PA_PAID_AT', nullable: true)]
    #[Groups(['payment:get'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PA_PAID_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['payment:get'])]
    private ?User $paidBy = null;

    #[ORM\Column(name: 'PA_CREATED_AT')]
    #[Groups(['payment:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function getMethodsAsList(): array
    {
        return [
            self::METHOD_MOBILE_MONEY,
            self::METHOD_CARD,
            self::METHOD_CASH,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(Ticket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProviderTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function setProviderTransactionId(?string $providerTransactionId): static
    {
        $this->providerTransactionId = $providerTransactionId;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderResponse(): ?array
    {
        return $this->providerResponse;
    }

    public function setProviderResponse(?array $providerResponse): static
    {
        $this->providerResponse = $providerResponse;

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

    public function getPaidBy(): ?User
    {
        return $this->paidBy;
    }

    public function setPaidBy(?User $paidBy): static
    {
        $this->paidBy = $paidBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function buildCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Get the value of providerWebhook
     */ 
    public function getProviderWebhook(): array|null
    {
        return $this->providerWebhook;
    }

    /**
     * Set the value of providerWebhook
     *
     * @return  self
     */ 
    public function setProviderWebhook(?array $providerWebhook): static
    {
        $this->providerWebhook = $providerWebhook;

        return $this;
    }
}
