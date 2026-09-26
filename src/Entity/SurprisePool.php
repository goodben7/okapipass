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
use App\Dto\Agency\CreateSurprisePoolDto;
use App\Dto\Agency\CreateSurprisePoolItemDto;
use App\Dto\Agency\DrawSurprisePoolDto;
use App\Dto\Agency\UpdateSurprisePoolDto;
use App\Model\RessourceInterface;
use App\Repository\SurprisePoolRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateSurprisePoolItemProcessor;
use App\State\Agency\CreateSurprisePoolProcessor;
use App\State\Agency\DrawSurprisePoolProcessor;
use App\State\Agency\UpdateSurprisePoolProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SurprisePoolRepository::class)]
#[ORM\Table(name: '`surprise_pool`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SurprisePool',
    normalizationContext: ['groups' => ['surprise_pool:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/surprise-pools',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/surprise-pools/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/surprise-pools',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateSurprisePoolDto::class,
            processor: CreateSurprisePoolProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/surprise-pools/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateSurprisePoolDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateSurprisePoolProcessor::class,
        ),
        new Post(
            uriTemplate: '/agency/surprise-pools/{id}/draw',
            security: AgencyPortalAccess::EXPRESSION,
            input: DrawSurprisePoolDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: DrawSurprisePoolProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/surprise-pools/{id}/items',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateSurprisePoolItemDto::class,
            processor: CreateSurprisePoolItemProcessor::class,
            status: 201,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'active' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'label'])]
class SurprisePool implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SP';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SP_ID', length: 16)]
    #[Groups(['surprise_pool:get', 'surprise_pool_item:get', 'loyalty_rule:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SP_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['surprise_pool:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'SP_LABEL', length: 160)]
    #[Groups(['surprise_pool:get', 'loyalty_rule:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'SP_ACTIVE')]
    #[Groups(['surprise_pool:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'SP_CREATED_AT')]
    #[Groups(['surprise_pool:get'])]
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
