<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateSchoolContractDto;
use App\Dto\Agency\UpdateSchoolContractDto;
use App\Model\RessourceInterface;
use App\Repository\SchoolContractRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateSchoolContractProcessor;
use App\State\Agency\DeleteSchoolContractProcessor;
use App\State\Agency\UpdateSchoolContractProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SchoolContractRepository::class)]
#[ORM\Table(name: '`school_contract`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SchoolContract',
    normalizationContext: ['groups' => ['school_contract:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/school-contracts',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/school-contracts/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/school-contracts',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateSchoolContractDto::class,
            processor: CreateSchoolContractProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/school-contracts/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateSchoolContractDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateSchoolContractProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/school-contracts/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
            processor: DeleteSchoolContractProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'schoolName' => 'ipartial',
    'offer.id' => 'exact',
    'transport.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['startDate', 'endDate', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'startDate', 'schoolName'])]
class SchoolContract implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SK';

    public const string STATUS_DRAFT = 'DRAFT';
    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_ENDED = 'ENDED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SK_ID', length: 16)]
    #[Groups(['school_contract:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SK_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['school_contract:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'SK_SCHOOL_NAME', length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['school_contract:get'])]
    private ?string $schoolName = null;

    #[ORM\Column(name: 'SK_SCHOOL_PHONE', length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Groups(['school_contract:get'])]
    private ?string $schoolPhone = null;

    #[ORM\Column(name: 'SK_SCHOOL_ADDRESS', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['school_contract:get'])]
    private ?string $schoolAddress = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SK_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    #[Groups(['school_contract:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SK_TRANSPORT', nullable: true, referencedColumnName: 'AT_ID')]
    #[Groups(['school_contract:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\Column(name: 'SK_START_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['school_contract:get'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(name: 'SK_END_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['school_contract:get'])]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(name: 'SK_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['school_contract:get'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'SK_MONTHLY_FEE')]
    #[Assert\PositiveOrZero]
    #[Groups(['school_contract:get'])]
    private int $monthlyFee = 0;

    #[ORM\Column(name: 'SK_CURRENCY', length: 3)]
    #[Assert\Length(exactly: 3)]
    #[Groups(['school_contract:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    /** @var list<array{code: string, label: string, order: int, time?: string}>|null */
    #[ORM\Column(name: 'SK_STOPS', type: Types::JSON, nullable: true)]
    #[Groups(['school_contract:get'])]
    private ?array $stops = null;

    #[ORM\Column(name: 'SK_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['school_contract:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'SK_CREATED_AT')]
    #[Groups(['school_contract:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'SK_UPDATED_AT', nullable: true)]
    #[Groups(['school_contract:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ACTIVE,
            self::STATUS_ENDED,
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

    public function getSchoolName(): ?string
    {
        return $this->schoolName;
    }

    public function setSchoolName(string $schoolName): static
    {
        $this->schoolName = $schoolName;

        return $this;
    }

    public function getSchoolPhone(): ?string
    {
        return $this->schoolPhone;
    }

    public function setSchoolPhone(?string $schoolPhone): static
    {
        $this->schoolPhone = $schoolPhone;

        return $this;
    }

    public function getSchoolAddress(): ?string
    {
        return $this->schoolAddress;
    }

    public function setSchoolAddress(?string $schoolAddress): static
    {
        $this->schoolAddress = $schoolAddress;

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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

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

    public function getMonthlyFee(): int
    {
        return $this->monthlyFee;
    }

    public function setMonthlyFee(int $monthlyFee): static
    {
        $this->monthlyFee = $monthlyFee;

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

    /**
     * @return list<array{code: string, label: string, order: int, time?: string}>|null
     */
    public function getStops(): ?array
    {
        return $this->stops;
    }

    /**
     * @param list<array{code: string, label: string, order: int, time?: string}>|null $stops
     */
    public function setStops(?array $stops): static
    {
        $this->stops = $stops;

        return $this;
    }

    /** @return list<string> */
    public function getStopCodes(): array
    {
        if (null === $this->stops || [] === $this->stops) {
            return [];
        }

        $codes = [];
        foreach ($this->stops as $stop) {
            if (isset($stop['code']) && '' !== (string) $stop['code']) {
                $codes[] = (string) $stop['code'];
            }
        }

        return $codes;
    }

    public function hasStopCode(string $code): bool
    {
        return \in_array($code, $this->getStopCodes(), true);
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
