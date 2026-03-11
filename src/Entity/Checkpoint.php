<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\CheckpointRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CheckpointRepository::class)]
#[ORM\Table(name: '`checkpoint`')]
#[ApiResource(
    normalizationContext: ['groups' => 'checkpoint:get'],
    operations: [
        new Get(
            security: 'is_granted("ROLE_CHECKPOINT_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            security: 'is_granted("ROLE_CHECKPOINT_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_CHECKPOINT_CREATE")',
            denormalizationContext: ['groups' => 'checkpoint:post'],
            processor: PersistProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_CHECKPOINT_UPDATE")',
            denormalizationContext: ['groups' => 'checkpoint:patch'],
            processor: PersistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'label' => 'ipartial',
    'active' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['label'])]
class Checkpoint implements RessourceInterface
{
    public const string ID_PREFIX = 'CP';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'CP_ID', length: 16)]
    #[Groups(['checkpoint:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'CP_LABEL', length: 120)]
    #[Groups(['checkpoint:get', 'checkpoint:post', 'checkpoint:patch'])]
    private ?string $label = null;

    #[ORM\Column(name: 'CP_ACTIVE')]
    #[Groups(['checkpoint:get', 'checkpoint:post', 'checkpoint:patch'])]
    private ?bool $active = null;

    #[ORM\Column(name: 'CP_LATITUDE', nullable: true)]
    #[Groups(['checkpoint:get', 'checkpoint:post', 'checkpoint:patch'])]
    private ?float $latitude = null;

    #[ORM\Column(name: 'CP_LONGITUDE', nullable: true)]
    #[Groups(['checkpoint:get', 'checkpoint:post', 'checkpoint:patch'])]
    private ?float $longitude = null;

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

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }
}
