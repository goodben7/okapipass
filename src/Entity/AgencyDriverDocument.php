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
use App\Dto\Agency\CreateAgencyDriverDocumentDto;
use App\Dto\Agency\UpdateAgencyDriverDocumentDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyDriverDocumentRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyDriverDocumentProcessor;
use App\State\Agency\DeleteAgencyDriverDocumentProcessor;
use App\State\Agency\UpdateAgencyDriverDocumentProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyDriverDocumentRepository::class)]
#[ORM\Table(name: '`agency_driver_document`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyDriverDocument',
    normalizationContext: ['groups' => ['agency_driver_document:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/driver-documents',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/driver-documents/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/driver-documents',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyDriverDocumentDto::class,
            processor: CreateAgencyDriverDocumentProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/driver-documents/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyDriverDocumentDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyDriverDocumentProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/driver-documents/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyDriverDocumentProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'type' => 'exact',
    'driver' => 'exact',
    'driver.id' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['issuedAt', 'expiresAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'expiresAt', 'label'])]
class AgencyDriverDocument implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'DD';

    public const string TYPE_PERMIT = 'PERMIT';
    public const string TYPE_TRAINING = 'TRAINING';
    public const string TYPE_SANCTION = 'SANCTION';
    public const string TYPE_INSURANCE = 'INSURANCE';
    public const string TYPE_OTHER = 'OTHER';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'DD_ID', length: 16)]
    #[Groups(['agency_driver_document:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DD_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_driver_document:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DD_DRIVER', nullable: false, referencedColumnName: 'AD_ID')]
    #[Groups(['agency_driver_document:get'])]
    private ?AgencyDriver $driver = null;

    #[ORM\Column(name: 'DD_TYPE', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getTypesAsList'])]
    #[Groups(['agency_driver_document:get'])]
    private string $type = self::TYPE_OTHER;

    #[ORM\Column(name: 'DD_LABEL', length: 160)]
    #[Groups(['agency_driver_document:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'DD_ISSUED_AT', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['agency_driver_document:get'])]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(name: 'DD_EXPIRES_AT', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['agency_driver_document:get'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'DD_FILE_URL', length: 512, nullable: true)]
    #[Groups(['agency_driver_document:get'])]
    private ?string $fileUrl = null;

    #[ORM\Column(name: 'DD_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_driver_document:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'DD_CREATED_AT')]
    #[Groups(['agency_driver_document:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getTypesAsList(): array
    {
        return [
            self::TYPE_PERMIT,
            self::TYPE_TRAINING,
            self::TYPE_SANCTION,
            self::TYPE_INSURANCE,
            self::TYPE_OTHER,
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

    public function getDriver(): ?AgencyDriver
    {
        return $this->driver;
    }

    public function setDriver(?AgencyDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

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

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): static
    {
        $this->fileUrl = $fileUrl;

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
