<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\AgencyBaggageExcessRepository;
use App\State\Agency\AgencyScopedItemProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyBaggageExcessRepository::class)]
#[ORM\Table(name: '`agency_baggage_excess`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyBaggageExcess',
    normalizationContext: ['groups' => ['agency_baggage_excess:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/baggage-excesses',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/baggage-excesses/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'status' => 'exact',
    'ticket.id' => 'exact',
    'ticket.reference' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class AgencyBaggageExcess implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'BX';

    public const string STATUS_RECORDED = 'RECORDED';
    public const string STATUS_PAID = 'PAID';
    public const string STATUS_WAIVED = 'WAIVED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'BX_ID', length: 16)]
    #[Groups(['agency_baggage_excess:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'BX_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'BX_TICKET', nullable: false, referencedColumnName: 'AK_ID')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?AgencyTicket $ticket = null;

    #[ORM\Column(name: 'BX_KG')]
    #[Assert\PositiveOrZero]
    #[Groups(['agency_baggage_excess:get'])]
    private ?int $kg = null;

    #[ORM\Column(name: 'BX_FREE_KG_APPLIED')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?int $freeKgApplied = null;

    #[ORM\Column(name: 'BX_EXCESS_KG')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?int $excessKg = null;

    #[ORM\Column(name: 'BX_UNIT_PRICE')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?int $unitPrice = null;

    #[ORM\Column(name: 'BX_AMOUNT')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?int $amount = null;

    #[ORM\Column(name: 'BX_CURRENCY', length: 3)]
    #[Groups(['agency_baggage_excess:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'BX_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['agency_baggage_excess:get'])]
    private string $status = self::STATUS_RECORDED;

    #[ORM\Column(name: 'BX_CREATED_AT')]
    #[Groups(['agency_baggage_excess:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [self::STATUS_RECORDED, self::STATUS_PAID, self::STATUS_WAIVED];
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

    public function getKg(): ?int
    {
        return $this->kg;
    }

    public function setKg(int $kg): static
    {
        $this->kg = $kg;

        return $this;
    }

    public function getFreeKgApplied(): ?int
    {
        return $this->freeKgApplied;
    }

    public function setFreeKgApplied(int $freeKgApplied): static
    {
        $this->freeKgApplied = $freeKgApplied;

        return $this;
    }

    public function getExcessKg(): ?int
    {
        return $this->excessKg;
    }

    public function setExcessKg(int $excessKg): static
    {
        $this->excessKg = $excessKg;

        return $this;
    }

    public function getUnitPrice(): ?int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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
