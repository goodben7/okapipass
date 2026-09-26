<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\AgencyOfferPriceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgencyOfferPriceHistoryRepository::class)]
#[ORM\Table(name: '`agency_offer_price_history`')]
#[ORM\HasLifecycleCallbacks]
class AgencyOfferPriceHistory implements RessourceInterface
{
    public const string ID_PREFIX = 'PH';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'PH_ID', length: 16)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'PH_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'PH_TICKET_PRICE')]
    private int $ticketPrice = 0;

    #[ORM\Column(name: 'PH_EFFECTIVE_FROM')]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\Column(name: 'PH_EFFECTIVE_TO', nullable: true)]
    private ?\DateTimeImmutable $effectiveTo = null;

    #[ORM\Column(name: 'PH_CREATED_AT')]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOffer(): ?AgencyOffer
    {
        return $this->offer;
    }

    public function setOffer(?AgencyOffer $offer): static
    {
        $this->offer = $offer;

        return $this;
    }

    public function getTicketPrice(): int
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(int $ticketPrice): static
    {
        $this->ticketPrice = $ticketPrice;

        return $this;
    }

    public function getEffectiveFrom(): ?\DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

        return $this;
    }

    public function getEffectiveTo(): ?\DateTimeImmutable
    {
        return $this->effectiveTo;
    }

    public function setEffectiveTo(?\DateTimeImmutable $effectiveTo): static
    {
        $this->effectiveTo = $effectiveTo;

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
        $this->effectiveFrom ??= $this->createdAt;
    }
}
