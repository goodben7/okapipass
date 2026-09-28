<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\IdempotencyRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IdempotencyRecordRepository::class)]
#[ORM\Table(name: '`idempotency_record`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDEMPOTENCY_SCOPE_KEY', fields: ['scope', 'keyHash'])]
#[ORM\HasLifecycleCallbacks]
class IdempotencyRecord implements RessourceInterface
{
    public const string ID_PREFIX = 'IK';

    public const string SCOPE_POS_SALE = 'pos_sale';
    public const string SCOPE_TICKET_CREATE = 'ticket_create';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'IK_ID', length: 16)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'IK_AGENCY', nullable: true, referencedColumnName: 'AG_ID')]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'IK_SCOPE', length: 40)]
    private ?string $scope = null;

    #[ORM\Column(name: 'IK_KEY_HASH', length: 64)]
    private ?string $keyHash = null;

    #[ORM\Column(name: 'IK_RESPONSE_STATUS')]
    private int $responseStatus = 200;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'IK_RESPONSE_BODY', type: Types::JSON, nullable: true)]
    private ?array $responseBody = null;

    #[ORM\Column(name: 'IK_CREATED_AT')]
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

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getKeyHash(): ?string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): static
    {
        $this->keyHash = $keyHash;

        return $this;
    }

    public function getResponseStatus(): int
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(int $responseStatus): static
    {
        $this->responseStatus = $responseStatus;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /** @param array<string, mixed>|null $responseBody */
    public function setResponseBody(?array $responseBody): static
    {
        $this->responseBody = $responseBody;

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
