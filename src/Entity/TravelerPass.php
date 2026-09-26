<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\TravelerPassRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TravelerPassRepository::class)]
#[ORM\Table(name: '`traveler_pass`')]
#[ORM\HasLifecycleCallbacks]
class TravelerPass implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'TP';

    public const string STATUS_ACTIVE = 'ACTIVE';
    public const string STATUS_EXHAUSTED = 'EXHAUSTED';
    public const string STATUS_EXPIRED = 'EXPIRED';
    public const string STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'TP_ID', length: 16)]
    #[Groups(['traveler_pass:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TP_USER', nullable: false, referencedColumnName: 'US_ID')]
    #[Groups(['traveler_pass:get'])]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TP_PRODUCT', nullable: false, referencedColumnName: 'PP_ID')]
    #[Groups(['traveler_pass:get'])]
    private ?TravelerPassProduct $product = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'TP_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['traveler_pass:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'TP_STATUS', length: 20)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['traveler_pass:get'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'TP_TRIPS_REMAINING', nullable: true)]
    #[Groups(['traveler_pass:get'])]
    private ?int $tripsRemaining = null;

    #[ORM\Column(name: 'TP_VALID_FROM', type: Types::DATE_IMMUTABLE)]
    #[Groups(['traveler_pass:get'])]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(name: 'TP_VALID_TO', type: Types::DATE_IMMUTABLE)]
    #[Groups(['traveler_pass:get'])]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(name: 'TP_PURCHASE_PRICE')]
    #[Groups(['traveler_pass:get'])]
    private int $purchasePrice = 0;

    #[ORM\Column(name: 'TP_CURRENCY', length: 3)]
    #[Groups(['traveler_pass:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'TP_CREATED_AT')]
    #[Groups(['traveler_pass:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_EXHAUSTED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getProduct(): ?TravelerPassProduct
    {
        return $this->product;
    }

    public function setProduct(?TravelerPassProduct $product): static
    {
        $this->product = $product;

        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTripsRemaining(): ?int
    {
        return $this->tripsRemaining;
    }

    public function setTripsRemaining(?int $tripsRemaining): static
    {
        $this->tripsRemaining = $tripsRemaining;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(\DateTimeImmutable $validTo): static
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function getPurchasePrice(): int
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(int $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

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
