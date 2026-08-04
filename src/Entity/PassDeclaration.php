<?php

namespace App\Entity;

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
use App\Dto\Agency\CreatePassDeclarationDto;
use App\Dto\Agency\ImportPassDeclarationCsvDto;
use App\Dto\Agency\UpdatePassDeclarationStatusDto;
use App\Model\RessourceInterface;
use App\Repository\PassDeclarationRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreatePassDeclarationProcessor;
use App\State\Agency\ImportPassDeclarationCsvProcessor;
use App\State\Agency\UpdatePassDeclarationStatusProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PassDeclarationRepository::class)]
#[ORM\Table(name: '`pass_declaration`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'PassDeclaration',
    normalizationContext: ['groups' => ['pass_declaration:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/declarations',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/declarations/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/declarations',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreatePassDeclarationDto::class,
            processor: CreatePassDeclarationProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/declarations/import-csv',
            security: 'is_granted("ROLE_PARTNER")',
            input: ImportPassDeclarationCsvDto::class,
            processor: ImportPassDeclarationCsvProcessor::class,
            inputFormats: [
                'json' => ['application/json'],
                'multipart' => ['multipart/form-data'],
            ],
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/declarations/{id}/status',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdatePassDeclarationStatusDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdatePassDeclarationStatusProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'source' => 'exact',
    'label' => 'ipartial',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'submittedAt', 'fptTotal'])]
class PassDeclaration implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'PD';

    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_SUBMITTED = 'submitted';
    public const string STATUS_PAID = 'paid';

    public const string SOURCE_MANUAL = 'manual';
    public const string SOURCE_CSV = 'csv';
    public const string SOURCE_EMBARKATION = 'embarkation';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PD_ID', length: 16)]
    #[Groups(['pass_declaration:get', 'agency_embarkation:get', 'agency_ticket:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PD_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['pass_declaration:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'PD_LABEL', length: 160)]
    #[Groups(['pass_declaration:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'PD_SOURCE', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getSourcesAsList'])]
    #[Groups(['pass_declaration:get'])]
    private string $source = self::SOURCE_MANUAL;

    #[ORM\OneToOne(mappedBy: 'declaration', targetEntity: AgencyEmbarkation::class)]
    #[Groups(['pass_declaration:get'])]
    private ?AgencyEmbarkation $embarkation = null;

    #[ORM\Column(name: 'PD_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['pass_declaration:get'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'PD_CURRENCY', length: 3)]
    #[Groups(['pass_declaration:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'PD_FPT_TOTAL')]
    #[Groups(['pass_declaration:get'])]
    private int $fptTotal = 0;

    /** @var Collection<int, DeclarationLine> */
    #[ORM\OneToMany(mappedBy: 'declaration', targetEntity: DeclarationLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['pass_declaration:get'])]
    private Collection $lines;

    /** @var Collection<int, AgencyTicket> */
    #[ORM\OneToMany(mappedBy: 'declaration', targetEntity: AgencyTicket::class)]
    private Collection $tickets;

    #[ORM\Column(name: 'PD_CREATED_AT')]
    #[Groups(['pass_declaration:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'PD_SUBMITTED_AT', nullable: true)]
    #[Groups(['pass_declaration:get'])]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(name: 'PD_PAID_AT', nullable: true)]
    #[Groups(['pass_declaration:get'])]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->tickets = new ArrayCollection();
    }

    public static function getStatusesAsList(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_PAID];
    }

    public static function getSourcesAsList(): array
    {
        return [self::SOURCE_MANUAL, self::SOURCE_CSV, self::SOURCE_EMBARKATION];
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getEmbarkation(): ?AgencyEmbarkation
    {
        return $this->embarkation;
    }

    public function setEmbarkation(?AgencyEmbarkation $embarkation): static
    {
        $this->embarkation = $embarkation;

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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getFptTotal(): int
    {
        return $this->fptTotal;
    }

    public function setFptTotal(int $fptTotal): static
    {
        $this->fptTotal = $fptTotal;

        return $this;
    }

    /** @return Collection<int, DeclarationLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(DeclarationLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setDeclaration($this);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

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

    public function recalculateFptTotal(): void
    {
        $total = 0;
        foreach ($this->lines as $line) {
            $total += $line->getPassPrice();
        }
        $this->fptTotal = $total;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
    }
}
