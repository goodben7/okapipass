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
use App\Domain\Agency\AgencyStaffRole;
use App\Dto\Agency\CreateAgencyStaffDto;
use App\Dto\Agency\UpdateAgencyStaffDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyStaffMemberRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyStaffProcessor;
use App\State\Agency\DeleteAgencyStaffProcessor;
use App\State\Agency\UpdateAgencyStaffProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyStaffMemberRepository::class)]
#[ORM\Table(name: '`agency_staff_member`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_STAFF_USER', fields: ['agency', 'user'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyStaffMember',
    normalizationContext: ['groups' => ['agency_staff:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/staff',
            security: 'is_granted("ROLE_PARTNER")',
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/staff/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/staff',
            security: 'is_granted("ROLE_PARTNER")',
            input: CreateAgencyStaffDto::class,
            processor: CreateAgencyStaffProcessor::class,
        ),
        new Patch(
            uriTemplate: '/agency/staff/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            input: UpdateAgencyStaffDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyStaffProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/staff/{id}',
            security: 'is_granted("ROLE_PARTNER")',
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyStaffProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'role' => 'exact',
    'active' => 'exact',
    'user.email' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'role'])]
class AgencyStaffMember implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SM';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SM_ID', length: 16)]
    #[Groups(['agency_staff:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SM_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_staff:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SM_USER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['agency_staff:get'])]
    private ?User $user = null;

    #[ORM\Column(name: 'SM_ROLE', length: 20)]
    #[Assert\Choice(callback: [AgencyStaffRole::class, 'all'])]
    #[Groups(['agency_staff:get'])]
    private string $role = AgencyStaffRole::READONLY;

    #[ORM\Column(name: 'SM_ACTIVE')]
    #[Groups(['agency_staff:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'SM_CREATED_AT')]
    #[Groups(['agency_staff:get'])]
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

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
