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
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\GenerateSchoolInvoiceDto;
use App\Dto\Agency\MarkSchoolInvoicePaidDto;
use App\Model\RessourceInterface;
use App\Repository\SchoolInvoiceRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CancelSchoolInvoiceProcessor;
use App\State\Agency\GenerateSchoolInvoiceProcessor;
use App\State\Agency\MarkSchoolInvoicePaidProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SchoolInvoiceRepository::class)]
#[ORM\Table(name: '`school_invoice`')]
#[ORM\UniqueConstraint(name: 'UNIQ_SCHOOL_INVOICE_CONTRACT_PERIOD', columns: ['IV_CONTRACT', 'IV_PERIOD_YM'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SchoolInvoice',
    normalizationContext: ['groups' => ['school_invoice:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/school-invoices',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/school-invoices/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/school-contracts/{id}/invoices/generate',
            security: AgencyPortalAccess::EXPRESSION,
            input: GenerateSchoolInvoiceDto::class,
            output: SchoolInvoice::class,
            normalizationContext: ['groups' => ['school_invoice:get']],
            read: false,
            processor: GenerateSchoolInvoiceProcessor::class,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/school-invoices/{id}/mark-paid',
            security: AgencyPortalAccess::EXPRESSION,
            input: MarkSchoolInvoicePaidDto::class,
            output: SchoolInvoice::class,
            normalizationContext: ['groups' => ['school_invoice:get']],
            provider: AgencyScopedItemProvider::class,
            processor: MarkSchoolInvoicePaidProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/school-invoices/{id}/cancel',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            output: SchoolInvoice::class,
            normalizationContext: ['groups' => ['school_invoice:get']],
            provider: AgencyScopedItemProvider::class,
            processor: CancelSchoolInvoiceProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'contract.id' => 'exact',
    'periodYm' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'periodYm', 'issuedAt'])]
class SchoolInvoice implements RessourceInterface, AgencyScopedInterface
{
    /** Prefix IV — do not reuse SI (SurprisePoolItem). */
    public const string ID_PREFIX = 'IV';

    public const string STATUS_DRAFT = 'DRAFT';
    public const string STATUS_ISSUED = 'ISSUED';
    public const string STATUS_PAID = 'PAID';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'IV_ID', length: 16)]
    #[Groups(['school_invoice:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'IV_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['school_invoice:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'IV_CONTRACT', nullable: false, referencedColumnName: 'SK_ID')]
    #[Groups(['school_invoice:get'])]
    private ?SchoolContract $contract = null;

    #[ORM\Column(name: 'IV_PERIOD_YM', length: 7)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-(0[1-9]|1[0-2])$/')]
    #[Groups(['school_invoice:get'])]
    private ?string $periodYm = null;

    #[ORM\Column(name: 'IV_AMOUNT')]
    #[Assert\PositiveOrZero]
    #[Groups(['school_invoice:get'])]
    private int $amount = 0;

    #[ORM\Column(name: 'IV_CURRENCY', length: 3)]
    #[Assert\Length(exactly: 3)]
    #[Groups(['school_invoice:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'IV_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['school_invoice:get'])]
    private string $status = self::STATUS_ISSUED;

    #[ORM\Column(name: 'IV_ISSUED_AT', nullable: true)]
    #[Groups(['school_invoice:get'])]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(name: 'IV_PAID_AT', nullable: true)]
    #[Groups(['school_invoice:get'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'IV_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['school_invoice:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'IV_CREATED_AT')]
    #[Groups(['school_invoice:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'IV_UPDATED_AT', nullable: true)]
    #[Groups(['school_invoice:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ISSUED,
            self::STATUS_PAID,
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

    public function getContract(): ?SchoolContract
    {
        return $this->contract;
    }

    public function setContract(?SchoolContract $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getPeriodYm(): ?string
    {
        return $this->periodYm;
    }

    public function setPeriodYm(string $periodYm): static
    {
        $this->periodYm = $periodYm;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

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
