<?php

namespace App\Entity;

use ApiPlatform\Metadata\Get;
use App\Doctrine\IdGenerator;
use ApiPlatform\Metadata\Post;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use App\Model\RessourceInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProfileRepository;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\Serializer\Attribute\Groups;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Doctrine\Common\State\PersistProcessor;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
#[ORM\Table(name: '`profile`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_PROFILE_PERSON_TYPE', fields: ['personType'])]
#[ApiResource(
    normalizationContext: ['groups' => 'profile:get'], 
    operations:[
        new Get(
            security: 'is_granted("ROLE_PROFILE_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            security: 'is_granted("ROLE_PROFILE_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_PROFILE_CREATE")',
            denormalizationContext: ['groups' => 'profile:post',],
            processor: PersistProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_PROFILE_UPDATE")',
            denormalizationContext: ['groups' => 'profile:patch',],
            processor: PersistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [ 
    'id' => 'exact',
    'label' => 'ipartial',
    'personType' => 'exact',
    'permissions' => 'exact',
    'active' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
class Profile implements RessourceInterface
{
    public const string ID_PREFIX = "PR";

    #[ORM\Id]
    #[ORM\GeneratedValue( strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PR_ID', length: 16)]
    #[Groups(['profile:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'PR_LABEL', length: 120)]
    #[Groups(['profile:get', 'profile:post', 'profile:patch'])]
    private ?string $label = null;

    #[ORM\Column(name: 'PR_PERSON_TYPE', length: 30)]
    #[Assert\Choice(callback: [User::class, 'getPersonTypesAsList'])]
    #[Groups(['profile:get', 'profile:post', 'profile:patch'])]
    private ?string $personType = null;

    #[ORM\Column(name: 'PR_PERMISSION', type: Types::SIMPLE_ARRAY)]
    #[Groups(['profile:get', 'profile:post', 'profile:patch'])]
    private array $permission = [];

    #[ORM\Column(name: 'PR_ACTIVE')]
    #[Groups(['profile:get', 'profile:post', 'profile:patch'])]
    private ?bool $active = null;

    #[ORM\Column(name: 'PR_CREATED_AT')]
    #[Groups(['profile:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'PR_UPDATED_AT', nullable: true)]
    #[Groups(['profile:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
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

    public function getPersonType(): ?string
    {
        return $this->personType;
    }

    public function setPersonType(string $personType): static
    {
        $this->personType = $personType;

        return $this;
    }

    public function getPermission(): array
    {
        return $this->permission;
    }

    public function setPermission(array $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function isActive(): ?bool
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

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function updateUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function buildCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
