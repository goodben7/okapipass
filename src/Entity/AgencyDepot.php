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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAgencyDepotDto;
use App\Dto\Agency\UpdateAgencyDepotDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyDepotRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyDepotProcessor;
use App\State\Agency\UpdateAgencyDepotProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyDepotRepository::class)]
#[ORM\Table(name: '`agency_depot`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_DEPOT_CODE', fields: ['agency', 'code'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyDepot',
    normalizationContext: ['groups' => ['agency_depot:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/depots',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/depots/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/depots',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyDepotDto::class,
            processor: CreateAgencyDepotProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/depots/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyDepotDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyDepotProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'code' => 'iexact',
    'active' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'code', 'label'])]
class AgencyDepot implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'DP';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'DP_ID', length: 16)]
    #[Groups(['agency_depot:get', 'pos_session:get', 'accounting_daily_close:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'DP_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_depot:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'DP_CODE', length: 40)]
    #[Groups(['agency_depot:get', 'pos_session:get', 'accounting_daily_close:get'])]
    private ?string $code = null;

    #[ORM\Column(name: 'DP_LABEL', length: 160)]
    #[Groups(['agency_depot:get', 'pos_session:get', 'accounting_daily_close:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'DP_ACTIVE')]
    #[Groups(['agency_depot:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'DP_CREATED_AT')]
    #[Groups(['agency_depot:get'])]
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper(trim($code));

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

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
