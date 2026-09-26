<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\AccountingJournalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AccountingJournalRepository::class)]
#[ORM\Table(name: '`accounting_journal`')]
#[ORM\Index(name: 'IDX_ACCOUNTING_JOURNAL_AGENCY_DATE', columns: ['AJ_AGENCY', 'AJ_ENTRY_DATE'])]
#[ORM\Index(name: 'IDX_ACCOUNTING_JOURNAL_SOURCE', columns: ['AJ_SOURCE_TYPE', 'AJ_SOURCE_ID'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyAccountingJournal',
    normalizationContext: ['groups' => ['accounting_journal:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/accounting/journal',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'account' => 'exact',
    'sourceType' => 'exact',
    'sourceId' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['entryDate'])]
class AccountingJournal implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AJ';

    public const string ACCOUNT_SALES = 'SALES';
    public const string ACCOUNT_CASH = 'CASH';
    public const string ACCOUNT_MM = 'MM';
    public const string ACCOUNT_CARD = 'CARD';
    public const string ACCOUNT_WALLET = 'WALLET';
    public const string ACCOUNT_COMMISSION = 'COMMISSION';
    public const string ACCOUNT_PASS_ONT = 'PASS_ONT';
    public const string ACCOUNT_VARIANCE = 'VARIANCE';

    public const string DIRECTION_DEBIT = 'DEBIT';
    public const string DIRECTION_CREDIT = 'CREDIT';

    public const string SOURCE_AGENCY_PAYMENT = 'AGENCY_PAYMENT';
    public const string SOURCE_CASH_HANDOVER = 'CASH_HANDOVER';
    public const string SOURCE_WALLET_TOPUP = 'WALLET_TOPUP';
    public const string SOURCE_MANUAL = 'MANUAL';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AJ_ID', length: 16)]
    #[Groups(['accounting_journal:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AJ_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['accounting_journal:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'AJ_ENTRY_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['accounting_journal:get'])]
    private ?\DateTimeImmutable $entryDate = null;

    #[ORM\Column(name: 'AJ_ACCOUNT', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getAccountsAsList'])]
    #[Groups(['accounting_journal:get'])]
    private string $account = self::ACCOUNT_SALES;

    #[ORM\Column(name: 'AJ_DIRECTION', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getDirectionsAsList'])]
    #[Groups(['accounting_journal:get'])]
    private string $direction = self::DIRECTION_DEBIT;

    #[ORM\Column(name: 'AJ_AMOUNT')]
    #[Groups(['accounting_journal:get'])]
    private int $amount = 0;

    #[ORM\Column(name: 'AJ_CURRENCY', length: 3)]
    #[Groups(['accounting_journal:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'AJ_SOURCE_TYPE', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getSourceTypesAsList'])]
    #[Groups(['accounting_journal:get'])]
    private string $sourceType = self::SOURCE_MANUAL;

    #[ORM\Column(name: 'AJ_SOURCE_ID', length: 16, nullable: true)]
    #[Groups(['accounting_journal:get'])]
    private ?string $sourceId = null;

    #[ORM\Column(name: 'AJ_LABEL', length: 160)]
    #[Groups(['accounting_journal:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'AJ_CREATED_AT')]
    #[Groups(['accounting_journal:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getAccountsAsList(): array
    {
        return [
            self::ACCOUNT_SALES,
            self::ACCOUNT_CASH,
            self::ACCOUNT_MM,
            self::ACCOUNT_CARD,
            self::ACCOUNT_WALLET,
            self::ACCOUNT_COMMISSION,
            self::ACCOUNT_PASS_ONT,
            self::ACCOUNT_VARIANCE,
        ];
    }

    /** @return list<string> */
    public static function getDirectionsAsList(): array
    {
        return [self::DIRECTION_DEBIT, self::DIRECTION_CREDIT];
    }

    /** @return list<string> */
    public static function getSourceTypesAsList(): array
    {
        return [
            self::SOURCE_AGENCY_PAYMENT,
            self::SOURCE_CASH_HANDOVER,
            self::SOURCE_WALLET_TOPUP,
            self::SOURCE_MANUAL,
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

    public function getEntryDate(): ?\DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function setEntryDate(\DateTimeImmutable $entryDate): static
    {
        $this->entryDate = $entryDate;

        return $this;
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    public function setAccount(string $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): static
    {
        $this->direction = $direction;

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

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(?string $sourceId): static
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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
