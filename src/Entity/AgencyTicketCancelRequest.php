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
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\RejectAgencyTicketCancelRequestDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyTicketCancelRequestRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\ApproveAgencyTicketCancelRequestProcessor;
use App\State\Agency\RejectAgencyTicketCancelRequestProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyTicketCancelRequestRepository::class)]
#[ORM\Table(name: '`agency_ticket_cancel_request`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyTicketCancelRequest',
    normalizationContext: ['groups' => ['agency_ticket_cancel_request:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/ticket-cancel-requests',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/ticket-cancel-requests/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/ticket-cancel-requests/{id}/approve',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ApproveAgencyTicketCancelRequestProcessor::class,
            status: 200,
        ),
        new Post(
            uriTemplate: '/agency/ticket-cancel-requests/{id}/reject',
            security: AgencyPortalAccess::EXPRESSION,
            input: RejectAgencyTicketCancelRequestDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: RejectAgencyTicketCancelRequestProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'ticket' => 'exact',
    'ticket.id' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'reviewedAt'])]
class AgencyTicketCancelRequest implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'CR';

    public const int CANCEL_WINDOW_HOURS = 2;

    public const string STATUS_PENDING = 'PENDING';
    public const string STATUS_APPROVED = 'APPROVED';
    public const string STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'CR_ID', length: 16)]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CR_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CR_TICKET', nullable: false, referencedColumnName: 'AK_ID')]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?AgencyTicket $ticket = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CR_REQUESTED_BY', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?User $requestedBy = null;

    #[ORM\Column(name: 'CR_REASON', type: Types::TEXT)]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?string $reason = null;

    #[ORM\Column(name: 'CR_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'CR_REVIEWED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?User $reviewedBy = null;

    #[ORM\Column(name: 'CR_REVIEWED_AT', nullable: true)]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(name: 'CR_REVIEW_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?string $reviewNotes = null;

    #[ORM\Column(name: 'CR_CREATED_AT')]
    #[Groups(['agency_ticket_cancel_request:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];
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

    public function getTicket(): ?AgencyTicket
    {
        return $this->ticket;
    }

    public function setTicket(?AgencyTicket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getReviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function setReviewNotes(?string $reviewNotes): static
    {
        $this->reviewNotes = $reviewNotes;

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
