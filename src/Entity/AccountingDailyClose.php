<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAccountingDailyCloseDto;
use App\Model\RessourceInterface;
use App\Repository\AccountingDailyCloseRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAccountingDailyCloseProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AccountingDailyCloseRepository::class)]
#[ORM\Table(name: '`accounting_daily_close`')]
#[ORM\UniqueConstraint(name: 'UNIQ_ACCOUNTING_DAILY_CLOSE_AGENCY_DATE', fields: ['agency', 'closeDate'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyAccountingDailyClose',
    normalizationContext: ['groups' => ['accounting_daily_close:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/accounting/daily-closes',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/accounting/daily-closes/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/accounting/daily-closes',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAccountingDailyCloseDto::class,
            processor: CreateAccountingDailyCloseProcessor::class,
            status: 201,
        ),
    ]
)]
#[ApiFilter(OrderFilter::class, properties: ['closeDate', 'closedAt', 'createdAt'])]
class AccountingDailyClose implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AC';

    public const string STATUS_OPEN = 'OPEN';
    public const string STATUS_CLOSED = 'CLOSED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AC_ID', length: 16)]
    #[Groups(['accounting_daily_close:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AC_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['accounting_daily_close:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AC_DEPOT', nullable: true, referencedColumnName: 'DP_ID')]
    #[Groups(['accounting_daily_close:get'])]
    private ?AgencyDepot $depot = null;

    #[ORM\Column(name: 'AC_CLOSE_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['accounting_daily_close:get'])]
    private ?\DateTimeImmutable $closeDate = null;

    #[ORM\Column(name: 'AC_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['accounting_daily_close:get'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'AC_SALES_TOTAL')]
    #[Groups(['accounting_daily_close:get'])]
    private int $salesTotal = 0;

    #[ORM\Column(name: 'AC_CASH_TOTAL')]
    #[Groups(['accounting_daily_close:get'])]
    private int $cashTotal = 0;

    #[ORM\Column(name: 'AC_MM_TOTAL')]
    #[Groups(['accounting_daily_close:get'])]
    private int $mmTotal = 0;

    #[ORM\Column(name: 'AC_CARD_TOTAL')]
    #[Groups(['accounting_daily_close:get'])]
    private int $cardTotal = 0;

    #[ORM\Column(name: 'AC_CURRENCY', length: 3)]
    #[Groups(['accounting_daily_close:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'AC_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['accounting_daily_close:get'])]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AC_CLOSED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['accounting_daily_close:get'])]
    private ?User $closedBy = null;

    #[ORM\Column(name: 'AC_CLOSED_AT', nullable: true)]
    #[Groups(['accounting_daily_close:get'])]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(name: 'AC_CREATED_AT')]
    #[Groups(['accounting_daily_close:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_OPEN, self::STATUS_CLOSED];
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

    public function getDepot(): ?AgencyDepot
    {
        return $this->depot;
    }

    public function setDepot(?AgencyDepot $depot): static
    {
        $this->depot = $depot;

        return $this;
    }

    public function getCloseDate(): ?\DateTimeImmutable
    {
        return $this->closeDate;
    }

    public function setCloseDate(\DateTimeImmutable $closeDate): static
    {
        $this->closeDate = $closeDate;

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

    public function getSalesTotal(): int
    {
        return $this->salesTotal;
    }

    public function setSalesTotal(int $salesTotal): static
    {
        $this->salesTotal = $salesTotal;

        return $this;
    }

    public function getCashTotal(): int
    {
        return $this->cashTotal;
    }

    public function setCashTotal(int $cashTotal): static
    {
        $this->cashTotal = $cashTotal;

        return $this;
    }

    public function getMmTotal(): int
    {
        return $this->mmTotal;
    }

    public function setMmTotal(int $mmTotal): static
    {
        $this->mmTotal = $mmTotal;

        return $this;
    }

    public function getCardTotal(): int
    {
        return $this->cardTotal;
    }

    public function setCardTotal(int $cardTotal): static
    {
        $this->cardTotal = $cardTotal;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

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
