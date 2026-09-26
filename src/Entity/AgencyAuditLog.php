<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\AgencyAuditLogRepository;
use App\Provider\Agency\AgencyAuditLogCollectionProvider;
use App\State\Agency\AgencyScopedItemProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AgencyAuditLogRepository::class)]
#[ORM\Table(name: '`agency_audit_log`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyAuditLog',
    normalizationContext: ['groups' => ['agency_audit_log:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/audit-logs',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyAuditLogCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/audit-logs/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'action' => 'exact',
    'entityType' => 'exact',
    'entityId' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class AgencyAuditLog implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'AL';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AL_ID', length: 16)]
    #[Groups(['agency_audit_log:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AL_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_audit_log:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AL_ACTOR', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['agency_audit_log:get'])]
    private ?User $actor = null;

    #[ORM\Column(name: 'AL_ACTION', length: 80)]
    #[Groups(['agency_audit_log:get'])]
    private ?string $action = null;

    #[ORM\Column(name: 'AL_ENTITY_TYPE', length: 80)]
    #[Groups(['agency_audit_log:get'])]
    private ?string $entityType = null;

    #[ORM\Column(name: 'AL_ENTITY_ID', length: 40)]
    #[Groups(['agency_audit_log:get'])]
    private ?string $entityId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'AL_META', type: Types::JSON, nullable: true)]
    #[Groups(['agency_audit_log:get'])]
    private ?array $meta = null;

    #[ORM\Column(name: 'AL_CREATED_AT')]
    #[Groups(['agency_audit_log:get'])]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /** @param array<string, mixed>|null $meta */
    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

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
