<?php

namespace App\Entity;

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
use App\Dto\Agency\CreateAgencyDriverDto;
use App\Dto\Agency\UpdateAgencyDriverDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyDriverRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyDriverProcessor;
use App\State\Agency\DeleteAgencyDriverProcessor;
use App\State\Agency\UpdateAgencyDriverProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyDriverRepository::class)]
#[ORM\Table(name: '`agency_driver`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_DRIVER_LICENSE', fields: ['agency', 'licenseNumber'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyDriver',
    normalizationContext: ['groups' => ['agency_driver:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/drivers',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/drivers/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/drivers',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyDriverDto::class,
            processor: CreateAgencyDriverProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/drivers/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyDriverDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyDriverProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/drivers/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyDriverProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'fullName' => 'ipartial',
    'phone' => 'exact',
    'licenseNumber' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'fullName', 'licenseExpiresAt'])]
class AgencyDriver implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AD';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_INACTIVE = 'INACTIVE';
    public const string STATUS_SUSPENDED = 'SUSPENDED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AD_ID', length: 16)]
    #[Groups(['agency_driver:get', 'agency_embarkation:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AD_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_driver:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'AD_FULL_NAME', length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[Groups(['agency_driver:get', 'agency_embarkation:get'])]
    private ?string $fullName = null;

    #[ORM\Column(name: 'AD_PHONE', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Groups(['agency_driver:get', 'agency_embarkation:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'AD_LICENSE_NUMBER', length: 40)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    #[Groups(['agency_driver:get'])]
    private ?string $licenseNumber = null;

    #[ORM\Column(name: 'AD_LICENSE_EXPIRES_AT', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['agency_driver:get'])]
    private ?\DateTimeImmutable $licenseExpiresAt = null;

    #[ORM\Column(name: 'AD_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_driver:get', 'agency_embarkation:get'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'AD_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_driver:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'AD_CREATED_AT')]
    #[Groups(['agency_driver:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'AD_UPDATED_AT', nullable: true)]
    #[Groups(['agency_driver:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_SUSPENDED,
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

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(string $licenseNumber): static
    {
        $this->licenseNumber = strtoupper(trim($licenseNumber));

        return $this;
    }

    public function getLicenseExpiresAt(): ?\DateTimeImmutable
    {
        return $this->licenseExpiresAt;
    }

    public function setLicenseExpiresAt(?\DateTimeImmutable $licenseExpiresAt): static
    {
        $this->licenseExpiresAt = $licenseExpiresAt;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAssignable(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
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
