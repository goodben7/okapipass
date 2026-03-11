<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use App\Repository\GoPassRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoPassRepository::class)]
#[ORM\Table(name: '`gopass`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_GOPASS_CODE', fields: ['code'])]
#[ApiResource(
    normalizationContext: ['groups' => 'gopass:get'],
    operations: [
        new Get(
            //security: 'is_granted("ROLE_GOPASS_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            //security: 'is_granted("ROLE_GOPASS_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_GOPASS_CREATE")',
            denormalizationContext: ['groups' => 'gopass:post'],
            processor: PersistProcessor::class,
        ),
        new Patch(
            security: 'is_granted("ROLE_GOPASS_UPDATE")',
            denormalizationContext: ['groups' => 'gopass:patch'],
            processor: PersistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'code' => 'exact',
    'label' => 'ipartial',
    'transportType' => 'exact',
    'currency' => 'exact',
    'active' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'price', 'label'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class GoPass implements RessourceInterface
{
    public const string ID_PREFIX = 'GP';

    public const string TRANSPORT_ROUTIER = 'ROUTIER';
    public const string TRANSPORT_FLUVIAL = 'FLUVIAL';
    public const string TRANSPORT_LACUSTRE = 'LACUSTRE';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'GP_ID', length: 16)]
    #[Groups(['gopass:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'GP_CODE', length: 50)]
    #[Assert\NotBlank]
    #[Groups(['gopass:get', 'gopass:post', 'gopass:patch'])]
    private ?string $code = null;

    #[ORM\Column(name: 'GP_LABEL', length: 120)]
    #[Assert\NotBlank]
    #[Groups(['gopass:get', 'gopass:post', 'gopass:patch'])]
    private ?string $label = null;

    #[ORM\Column(name: 'GP_TRANSPORT_TYPE', length: 10)]
    #[Assert\Choice(callback: [self::class, 'getTransportTypesAsList'])]
    #[Groups(['gopass:get', 'gopass:post', 'gopass:patch'])]
    private ?string $transportType = null;

    #[ORM\Column(name: 'GP_PRICE')]
    #[Assert\NotNull]
    #[Groups(['gopass:get', 'gopass:post', 'gopass:patch'])]
    private ?float $price = null;

    #[ORM\Column(name: 'GP_CURRENCY', length: 3)]
    #[Assert\NotBlank]
    #[Assert\Currency]
    #[Groups(['gopass:get', 'gopass:post', 'gopass:patch'])]
    private ?string $currency = null;

    #[ORM\Column(name: 'GP_ACTIVE')]
    #[Groups(['gopass:get', 'gopass:post', 'gopass:patch'])]
    private ?bool $active = null;

    #[ORM\Column(name: 'GP_CREATED_AT')]
    #[Groups(['gopass:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public static function getTransportTypesAsList(): array
    {
        return [
            self::TRANSPORT_ROUTIER,
            self::TRANSPORT_FLUVIAL,
            self::TRANSPORT_LACUSTRE,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getTransportType(): ?string
    {
        return $this->transportType;
    }

    public function setTransportType(string $transportType): static
    {
        $this->transportType = $transportType;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

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

    #[ORM\PrePersist]
    public function buildCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
