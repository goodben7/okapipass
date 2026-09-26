<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
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
use App\Dto\Agency\CreateAgencyBlacklistDto;
use App\Dto\Agency\UpdateAgencyBlacklistDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyBlacklistEntryRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyBlacklistProcessor;
use App\State\Agency\DeleteAgencyBlacklistProcessor;
use App\State\Agency\UpdateAgencyBlacklistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyBlacklistEntryRepository::class)]
#[ORM\Table(name: '`agency_blacklist_entry`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyBlacklistEntry',
    normalizationContext: ['groups' => ['agency_blacklist:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/blacklist',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/blacklist/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/blacklist',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyBlacklistDto::class,
            processor: CreateAgencyBlacklistProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/blacklist/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyBlacklistDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyBlacklistProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/blacklist/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyBlacklistProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'type' => 'exact',
    'value' => 'iexact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class AgencyBlacklistEntry implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'BL';

    public const string TYPE_PHONE = 'PHONE';
    public const string TYPE_ID_DOCUMENT = 'ID_DOCUMENT';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'BL_ID', length: 16)]
    #[Groups(['agency_blacklist:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'BL_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_blacklist:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'BL_TYPE', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getTypesAsList'])]
    #[Groups(['agency_blacklist:get'])]
    private ?string $type = null;

    #[ORM\Column(name: 'BL_VALUE', length: 120)]
    #[Groups(['agency_blacklist:get'])]
    private ?string $value = null;

    #[ORM\Column(name: 'BL_REASON', length: 255, nullable: true)]
    #[Groups(['agency_blacklist:get'])]
    private ?string $reason = null;

    #[ORM\Column(name: 'BL_ACTIVE')]
    #[Groups(['agency_blacklist:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'BL_CREATED_AT')]
    #[Groups(['agency_blacklist:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @return list<string>
     */
    public static function getTypesAsList(): array
    {
        return [self::TYPE_PHONE, self::TYPE_ID_DOCUMENT];
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = strtoupper(trim($type));

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = trim($value);

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

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
