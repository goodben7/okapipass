<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Dto\Traveler\CreateTravelerBeneficiaryDto;
use App\Dto\Traveler\UpdateTravelerBeneficiaryDto;
use App\Model\RessourceInterface;
use App\Provider\Traveler\TravelerBeneficiaryCollectionProvider;
use App\Provider\Traveler\TravelerBeneficiaryItemProvider;
use App\Repository\TravelerBeneficiaryRepository;
use App\State\Traveler\CreateTravelerBeneficiaryProcessor;
use App\State\Traveler\DeleteTravelerBeneficiaryProcessor;
use App\State\Traveler\UpdateTravelerBeneficiaryProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TravelerBeneficiaryRepository::class)]
#[ORM\Table(name: '`traveler_beneficiary`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'TravelerBeneficiary',
    normalizationContext: ['groups' => ['traveler_beneficiary:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/traveler/family',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerBeneficiaryCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/traveler/family/{id}',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerBeneficiaryItemProvider::class,
        ),
        new Post(
            uriTemplate: '/traveler/family',
            security: 'is_granted("ROLE_TRAVELER")',
            input: CreateTravelerBeneficiaryDto::class,
            processor: CreateTravelerBeneficiaryProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/traveler/family/{id}',
            security: 'is_granted("ROLE_TRAVELER")',
            input: UpdateTravelerBeneficiaryDto::class,
            provider: TravelerBeneficiaryItemProvider::class,
            processor: UpdateTravelerBeneficiaryProcessor::class,
        ),
        new Delete(
            uriTemplate: '/traveler/family/{id}',
            security: 'is_granted("ROLE_TRAVELER")',
            provider: TravelerBeneficiaryItemProvider::class,
            processor: DeleteTravelerBeneficiaryProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'relation' => 'exact',
    'phone' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'fullName'])]
class TravelerBeneficiary implements RessourceInterface
{
    public const string ID_PREFIX = 'TB';

    public const string RELATION_CHILD = 'CHILD';
    public const string RELATION_SPOUSE = 'SPOUSE';
    public const string RELATION_PARENT = 'PARENT';
    public const string RELATION_OTHER = 'OTHER';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TB_ID', length: 16)]
    #[Groups(['traveler_beneficiary:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TB_OWNER', nullable: false, referencedColumnName: 'US_ID')]
    private ?User $owner = null;

    #[ORM\Column(name: 'TB_FULL_NAME', length: 160)]
    #[Groups(['traveler_beneficiary:get'])]
    private ?string $fullName = null;

    #[ORM\Column(name: 'TB_PHONE', length: 20)]
    #[Groups(['traveler_beneficiary:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'TB_RELATION', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getRelationsAsList'])]
    #[Groups(['traveler_beneficiary:get'])]
    private string $relation = self::RELATION_OTHER;

    #[ORM\Column(name: 'TB_DATE_OF_BIRTH', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['traveler_beneficiary:get'])]
    private ?\DateTimeImmutable $dateOfBirth = null;

    #[ORM\Column(name: 'TB_ID_DOCUMENT', length: 80, nullable: true)]
    #[Groups(['traveler_beneficiary:get'])]
    private ?string $idDocument = null;

    #[ORM\Column(name: 'TB_CREATED_AT')]
    #[Groups(['traveler_beneficiary:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getRelationsAsList(): array
    {
        return [
            self::RELATION_CHILD,
            self::RELATION_SPOUSE,
            self::RELATION_PARENT,
            self::RELATION_OTHER,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

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

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function setRelation(string $relation): static
    {
        $this->relation = $relation;

        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeImmutable $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getIdDocument(): ?string
    {
        return $this->idDocument;
    }

    public function setIdDocument(?string $idDocument): static
    {
        $this->idDocument = $idDocument;

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
