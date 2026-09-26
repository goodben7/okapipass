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
use App\Dto\Agency\CreateAgencyPassProductDto;
use App\Dto\Agency\UpdateAgencyPassProductDto;
use App\Model\RessourceInterface;
use App\Provider\Traveler\TravelerPassProductCollectionProvider;
use App\Repository\TravelerPassProductRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyPassProductProcessor;
use App\State\Agency\UpdateAgencyPassProductProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TravelerPassProductRepository::class)]
#[ORM\Table(name: '`traveler_pass_product`')]
#[ORM\UniqueConstraint(name: 'UNIQ_TRAVELER_PASS_PRODUCT_AGENCY_CODE', fields: ['agency', 'code'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'TravelerPassProduct',
    normalizationContext: ['groups' => ['traveler_pass_product:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/pass-products',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/pass-products/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/pass-products',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyPassProductDto::class,
            processor: CreateAgencyPassProductProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/pass-products/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyPassProductDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyPassProductProcessor::class,
        ),
        new GetCollection(
            uriTemplate: '/traveler/pass-products',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerPassProductCollectionProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'code' => 'iexact',
    'active' => 'exact',
    'agency' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'label', 'price'])]
class TravelerPassProduct implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'PP';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PP_ID', length: 16)]
    #[Groups(['traveler_pass_product:get', 'traveler_pass:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PP_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['traveler_pass_product:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'PP_CODE', length: 40)]
    #[Groups(['traveler_pass_product:get', 'traveler_pass:get'])]
    private ?string $code = null;

    #[ORM\Column(name: 'PP_LABEL', length: 160)]
    #[Groups(['traveler_pass_product:get', 'traveler_pass:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'PP_ORIGIN', length: 120, nullable: true)]
    #[Groups(['traveler_pass_product:get'])]
    private ?string $origin = null;

    #[ORM\Column(name: 'PP_DESTINATION', length: 120, nullable: true)]
    #[Groups(['traveler_pass_product:get'])]
    private ?string $destination = null;

    /** Null = unlimited trips within validity window. */
    #[ORM\Column(name: 'PP_TRIPS_ALLOWED', nullable: true)]
    #[Groups(['traveler_pass_product:get', 'traveler_pass:get'])]
    private ?int $tripsAllowed = null;

    #[ORM\Column(name: 'PP_VALIDITY_DAYS')]
    #[Groups(['traveler_pass_product:get'])]
    private int $validityDays = 1;

    #[ORM\Column(name: 'PP_PRICE')]
    #[Groups(['traveler_pass_product:get'])]
    private int $price = 0;

    #[ORM\Column(name: 'PP_CURRENCY', length: 3)]
    #[Groups(['traveler_pass_product:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'PP_ACTIVE')]
    #[Groups(['traveler_pass_product:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'PP_CREATED_AT')]
    #[Groups(['traveler_pass_product:get'])]
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

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getTripsAllowed(): ?int
    {
        return $this->tripsAllowed;
    }

    public function setTripsAllowed(?int $tripsAllowed): static
    {
        $this->tripsAllowed = $tripsAllowed;

        return $this;
    }

    public function getValidityDays(): int
    {
        return $this->validityDays;
    }

    public function setValidityDays(int $validityDays): static
    {
        $this->validityDays = $validityDays;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

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
