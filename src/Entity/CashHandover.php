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
use App\Dto\Agency\CreateCashHandoverDto;
use App\Dto\Agency\RejectCashHandoverDto;
use App\Model\RessourceInterface;
use App\Repository\CashHandoverRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\ConfirmCashHandoverProcessor;
use App\State\Agency\CreateCashHandoverProcessor;
use App\State\Agency\RejectCashHandoverProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CashHandoverRepository::class)]
#[ORM\Table(name: '`cash_handover`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'CashHandover',
    normalizationContext: ['groups' => ['cash_handover:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/pos/cash-handovers',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/pos/cash-handovers/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/pos/cash-handovers',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateCashHandoverDto::class,
            processor: CreateCashHandoverProcessor::class,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/pos/cash-handovers/{id}/confirm',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ConfirmCashHandoverProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/pos/cash-handovers/{id}/reject',
            security: AgencyPortalAccess::EXPRESSION,
            input: RejectCashHandoverDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: RejectCashHandoverProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/pos/cash-handovers/{id}/resolve',
            security: AgencyPortalAccess::EXPRESSION,
            input: \App\Dto\Agency\ResolveCashHandoverDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: \App\State\Agency\ResolveCashHandoverProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'seller' => 'exact',
    'session' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'confirmedAt', 'declaredAmount'])]
class CashHandover implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'CH';

    public const string STATUS_PENDING = 'PENDING';
    public const string STATUS_CONFIRMED = 'CONFIRMED';
    public const string STATUS_REJECTED = 'REJECTED';
    public const string STATUS_RESOLVED = 'RESOLVED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'CH_ID', length: 16)]
    #[Groups(['cash_handover:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CH_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['cash_handover:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CH_SESSION', nullable: false, referencedColumnName: 'PS_ID')]
    #[Groups(['cash_handover:get'])]
    private ?PosSession $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CH_SELLER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['cash_handover:get'])]
    private ?User $seller = null;

    #[ORM\Column(name: 'CH_DECLARED_AMOUNT')]
    #[Groups(['cash_handover:get'])]
    private int $declaredAmount = 0;

    #[ORM\Column(name: 'CH_EXPECTED_CASH', options: ['default' => 0])]
    #[Groups(['cash_handover:get'])]
    private int $expectedCash = 0;

    #[ORM\Column(name: 'CH_VARIANCE', options: ['default' => 0])]
    #[Groups(['cash_handover:get'])]
    private int $variance = 0;

    #[ORM\Column(name: 'CH_CURRENCY', length: 3)]
    #[Groups(['cash_handover:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'CH_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['cash_handover:get'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CH_CONFIRMED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['cash_handover:get'])]
    private ?User $confirmedBy = null;

    #[ORM\Column(name: 'CH_CONFIRMED_AT', nullable: true)]
    #[Groups(['cash_handover:get'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(name: 'CH_REJECTION_REASON', length: 500, nullable: true)]
    #[Groups(['cash_handover:get'])]
    private ?string $rejectionReason = null;

    #[ORM\Column(name: 'CH_RESOLUTION_JUSTIFICATION', length: 500, nullable: true)]
    #[Groups(['cash_handover:get'])]
    private ?string $resolutionJustification = null;

    #[ORM\Column(name: 'CH_RESOLVED_AT', nullable: true)]
    #[Groups(['cash_handover:get'])]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CH_RESOLVED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['cash_handover:get'])]
    private ?User $resolvedBy = null;

    #[ORM\Column(name: 'CH_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['cash_handover:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'CH_CREATED_AT')]
    #[Groups(['cash_handover:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_REJECTED, self::STATUS_RESOLVED];
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

    public function getSession(): ?PosSession
    {
        return $this->session;
    }

    public function setSession(?PosSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function setSeller(?User $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getDeclaredAmount(): int
    {
        return $this->declaredAmount;
    }

    public function setDeclaredAmount(int $declaredAmount): static
    {
        $this->declaredAmount = $declaredAmount;

        return $this;
    }

    public function getExpectedCash(): int
    {
        return $this->expectedCash;
    }

    public function setExpectedCash(int $expectedCash): static
    {
        $this->expectedCash = $expectedCash;

        return $this;
    }

    public function getVariance(): int
    {
        return $this->variance;
    }

    public function setVariance(int $variance): static
    {
        $this->variance = $variance;

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

    public function getConfirmedBy(): ?User
    {
        return $this->confirmedBy;
    }

    public function setConfirmedBy(?User $confirmedBy): static
    {
        $this->confirmedBy = $confirmedBy;

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

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getResolutionJustification(): ?string
    {
        return $this->resolutionJustification;
    }

    public function setResolutionJustification(?string $resolutionJustification): static
    {
        $this->resolutionJustification = $resolutionJustification;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?User $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;

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
        $this->createdAt ??= new \DateTimeImmutable('now');
    }
}
