<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\AgencyTripAssignmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Audit journal for bus ↔ course (embarkation) assignments.
 * Prefix TA. Created by assign/reassign/unassign — no full CRUD.
 */
#[ORM\Entity(repositoryClass: AgencyTripAssignmentRepository::class)]
#[ORM\Table(name: '`agency_trip_assignment`')]
#[ApiResource(
    shortName: 'AgencyTripAssignment',
    normalizationContext: ['groups' => ['agency_trip_assignment:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/trip-assignments',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'embarkation' => 'exact',
    'embarkation.id' => 'exact',
    'transport.id' => 'exact',
    'driver.id' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['assignedAt'])]
class AgencyTripAssignment implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'TA';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TA_ID', length: 16)]
    #[Groups(['agency_trip_assignment:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TA_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_trip_assignment:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TA_EMBARKATION', nullable: false, referencedColumnName: 'AE_ID')]
    #[Groups(['agency_trip_assignment:get'])]
    private ?AgencyEmbarkation $embarkation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TA_TRANSPORT', nullable: false, referencedColumnName: 'AT_ID')]
    #[Groups(['agency_trip_assignment:get'])]
    private ?AgencyTransport $transport = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TA_DRIVER', nullable: true, referencedColumnName: 'AD_ID')]
    #[Groups(['agency_trip_assignment:get'])]
    private ?AgencyDriver $driver = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TA_ASSIGNED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['agency_trip_assignment:get'])]
    private ?User $assignedBy = null;

    #[ORM\Column(name: 'TA_ASSIGNED_AT')]
    #[Groups(['agency_trip_assignment:get'])]
    private ?\DateTimeImmutable $assignedAt = null;

    #[ORM\Column(name: 'TA_UNASSIGNED_AT', nullable: true)]
    #[Groups(['agency_trip_assignment:get'])]
    private ?\DateTimeImmutable $unassignedAt = null;

    #[ORM\Column(name: 'TA_REASON', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_trip_assignment:get'])]
    private ?string $reason = null;

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

    public function getEmbarkation(): ?AgencyEmbarkation
    {
        return $this->embarkation;
    }

    public function setEmbarkation(?AgencyEmbarkation $embarkation): static
    {
        $this->embarkation = $embarkation;

        return $this;
    }

    public function getTransport(): ?AgencyTransport
    {
        return $this->transport;
    }

    public function setTransport(?AgencyTransport $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function getDriver(): ?AgencyDriver
    {
        return $this->driver;
    }

    public function setDriver(?AgencyDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(\DateTimeImmutable $assignedAt): static
    {
        $this->assignedAt = $assignedAt;

        return $this;
    }

    public function getUnassignedAt(): ?\DateTimeImmutable
    {
        return $this->unassignedAt;
    }

    public function setUnassignedAt(?\DateTimeImmutable $unassignedAt): static
    {
        $this->unassignedAt = $unassignedAt;

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
}
