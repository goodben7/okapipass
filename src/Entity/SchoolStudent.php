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
use App\Dto\Agency\CreateSchoolStudentDto;
use App\Dto\Agency\UpdateSchoolStudentDto;
use App\Model\RessourceInterface;
use App\Repository\SchoolStudentRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateSchoolStudentProcessor;
use App\State\Agency\DeleteSchoolStudentProcessor;
use App\State\Agency\UpdateSchoolStudentProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SchoolStudentRepository::class)]
#[ORM\Table(name: '`school_student`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'SchoolStudent',
    normalizationContext: ['groups' => ['school_student:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/school-students',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/school-students/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/school-students',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateSchoolStudentDto::class,
            processor: CreateSchoolStudentProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/school-students/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateSchoolStudentDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateSchoolStudentProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/school-students/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
            processor: DeleteSchoolStudentProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'contract.id' => 'exact',
    'fullName' => 'ipartial',
    'externalRef' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'fullName'])]
class SchoolStudent implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SU';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SU_ID', length: 16)]
    #[Groups(['school_student:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SU_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['school_student:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SU_CONTRACT', nullable: false, referencedColumnName: 'SK_ID')]
    #[Groups(['school_student:get'])]
    private ?SchoolContract $contract = null;

    #[ORM\Column(name: 'SU_FULL_NAME', length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['school_student:get'])]
    private ?string $fullName = null;

    #[ORM\Column(name: 'SU_PHONE', length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    #[Groups(['school_student:get'])]
    private ?string $phone = null;

    #[ORM\Column(name: 'SU_GRADE', length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    #[Groups(['school_student:get'])]
    private ?string $grade = null;

    #[ORM\Column(name: 'SU_PICKUP_STOP_CODE', length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    #[Groups(['school_student:get'])]
    private ?string $pickupStopCode = null;

    #[ORM\Column(name: 'SU_DROPOFF_STOP_CODE', length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    #[Groups(['school_student:get'])]
    private ?string $dropoffStopCode = null;

    #[ORM\Column(name: 'SU_ACTIVE')]
    #[Groups(['school_student:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'SU_EXTERNAL_REF', length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    #[Groups(['school_student:get'])]
    private ?string $externalRef = null;

    #[ORM\Column(name: 'SU_CREATED_AT')]
    #[Groups(['school_student:get'])]
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

    public function getContract(): ?SchoolContract
    {
        return $this->contract;
    }

    public function setContract(?SchoolContract $contract): static
    {
        $this->contract = $contract;

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

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function setGrade(?string $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getPickupStopCode(): ?string
    {
        return $this->pickupStopCode;
    }

    public function setPickupStopCode(?string $pickupStopCode): static
    {
        $this->pickupStopCode = $pickupStopCode;

        return $this;
    }

    public function getDropoffStopCode(): ?string
    {
        return $this->dropoffStopCode;
    }

    public function setDropoffStopCode(?string $dropoffStopCode): static
    {
        $this->dropoffStopCode = $dropoffStopCode;

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

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): static
    {
        $this->externalRef = $externalRef;

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
