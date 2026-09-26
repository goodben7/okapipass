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
use App\Dto\Agency\OpenPosSessionDto;
use App\Model\RessourceInterface;
use App\Repository\PosSessionRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\ClosePosSessionProcessor;
use App\State\Agency\OpenPosSessionProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PosSessionRepository::class)]
#[ORM\Table(name: '`pos_session`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'PosSession',
    normalizationContext: ['groups' => ['pos_session:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/pos/sessions',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/pos/sessions/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/pos/sessions/open',
            security: AgencyPortalAccess::EXPRESSION,
            input: OpenPosSessionDto::class,
            processor: OpenPosSessionProcessor::class,
            status: 201,
        ),
        new Post(
            uriTemplate: '/agency/pos/sessions/{id}/close',
            security: AgencyPortalAccess::EXPRESSION,
            input: false,
            deserialize: false,
            validate: false,
            provider: AgencyScopedItemProvider::class,
            processor: ClosePosSessionProcessor::class,
            status: 200,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'seller' => 'exact',
    'pointOfSale' => 'ipartial',
])]
#[ApiFilter(OrderFilter::class, properties: ['openedAt', 'closedAt', 'createdAt'])]
class PosSession implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'PS';

    public const string STATUS_OPEN = 'OPEN';
    public const string STATUS_CLOSED = 'CLOSED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PS_ID', length: 16)]
    #[Groups(['pos_session:get', 'cash_handover:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PS_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['pos_session:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PS_SELLER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['pos_session:get', 'cash_handover:get'])]
    private ?User $seller = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PS_DEPOT', nullable: true, referencedColumnName: 'DP_ID')]
    #[Groups(['pos_session:get'])]
    private ?AgencyDepot $depot = null;

    #[ORM\Column(name: 'PS_POINT_OF_SALE', length: 80, nullable: true)]
    #[Groups(['pos_session:get'])]
    private ?string $pointOfSale = null;

    #[ORM\Column(name: 'PS_STATUS', length: 12)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['pos_session:get', 'cash_handover:get'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(name: 'PS_OPENED_AT')]
    #[Groups(['pos_session:get'])]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(name: 'PS_CLOSED_AT', nullable: true)]
    #[Groups(['pos_session:get'])]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(name: 'PS_EXPECTED_CASH', options: ['default' => 0])]
    #[Groups(['pos_session:get'])]
    private int $expectedCash = 0;

    #[ORM\Column(name: 'PS_NOTES', type: Types::TEXT, nullable: true)]
    #[Groups(['pos_session:get'])]
    private ?string $notes = null;

    #[ORM\Column(name: 'PS_DEVICE_ID', length: 80, nullable: true)]
    #[Groups(['pos_session:get'])]
    private ?string $deviceId = null;

    #[ORM\Column(name: 'PS_CREATED_AT')]
    #[Groups(['pos_session:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_OPEN, self::STATUS_CLOSED];
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

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function setSeller(?User $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getDepot(): ?AgencyDepot
    {
        return $this->depot;
    }

    public function setDepot(?AgencyDepot $depot): static
    {
        $this->depot = $depot;

        return $this;
    }

    public function getPointOfSale(): ?string
    {
        return $this->pointOfSale;
    }

    public function setPointOfSale(?string $pointOfSale): static
    {
        $this->pointOfSale = $pointOfSale;

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

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getExpectedCash(): int
    {
        return $this->expectedCash;
    }

    public function setExpectedCash(int $expectedCash): static
    {
        $this->expectedCash = $expectedCash;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): static
    {
        $this->deviceId = null !== $deviceId && '' !== trim($deviceId) ? trim($deviceId) : null;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->createdAt ??= $now;
        $this->openedAt ??= $now;
    }
}
