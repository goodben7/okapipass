<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Dto\VerifyTicketDto;
use App\Model\RessourceInterface;
use App\Repository\TicketVerificationRepository;
use App\State\CreateTicketVerificationProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketVerificationRepository::class)]
#[ORM\Table(name: '`ticket_verification`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => 'ticket_verification:get'],
    operations: [
        new Get(
            security: 'is_granted("ROLE_TICKET_VERIFIER_DETAILS")',
            provider: ItemProvider::class
        ),
        new GetCollection(
            security: 'is_granted("ROLE_TICKET_VERIFIER_LIST")',
            provider: CollectionProvider::class
        ),
        new Post(
            security: 'is_granted("ROLE_TICKET_VERIFIER_CREATE")',
            input: VerifyTicketDto::class,
            processor: CreateTicketVerificationProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'ticket.id' => 'exact',
    'verifiedBy.id' => 'exact',
    'checkpoint.id' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['verifiedAt'])]
#[ApiFilter(DateFilter::class, properties: ['verifiedAt'])]
class TicketVerification implements RessourceInterface
{
    public const string ID_PREFIX = 'TV';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TV_ID', length: 16)]
    #[Groups(['ticket_verification:get', 'ticket:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'verifications')]
    #[ORM\JoinColumn(name: 'TV_TICKET', referencedColumnName: 'TI_ID', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['ticket_verification:get'])]
    private ?Ticket $ticket = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TV_VERIFIER', referencedColumnName: 'US_ID', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['ticket_verification:get', 'ticket:get'])]
    private ?User $verifiedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TV_CHECKPOINT', referencedColumnName: 'CP_ID', nullable: false)]
    #[Assert\NotNull]
    #[Groups(['ticket_verification:get', 'ticket:get'])]
    private ?Checkpoint $checkpoint = null;

    #[ORM\Column(name: 'TV_VERIFIED_AT')]
    #[Groups(['ticket_verification:get', 'ticket:get'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(name: 'TV_COMMENT', type: 'text', nullable: true)]
    #[Groups(['ticket_verification:get'])]
    private ?string $comment = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getCheckpoint(): ?Checkpoint
    {
        return $this->checkpoint;
    }

    public function setCheckpoint(?Checkpoint $checkpoint): static
    {
        $this->checkpoint = $checkpoint;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->verifiedAt = new \DateTimeImmutable();
    }

    /**
     * Get the value of verifiedBy
     */ 
    public function getVerifiedBy(): ?User
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?User $verifiedBy): static
    {
        $this->verifiedBy = $verifiedBy;

        return $this;
    }
}
