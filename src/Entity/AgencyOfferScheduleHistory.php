<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\AgencyOfferScheduleHistoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AgencyOfferScheduleHistoryRepository::class)]
#[ORM\Table(name: '`agency_offer_schedule_history`')]
#[ORM\HasLifecycleCallbacks]
class AgencyOfferScheduleHistory implements RessourceInterface
{
    public const string ID_PREFIX = 'SH';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SH_ID', length: 16)]
    #[Groups(['agency_offer_schedule_history:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SH_OFFER', nullable: false, referencedColumnName: 'AO_ID')]
    #[Groups(['agency_offer_schedule_history:get'])]
    private ?AgencyOffer $offer = null;

    #[ORM\Column(name: 'SH_DEPARTURE_TIME', length: 5)]
    #[Groups(['agency_offer_schedule_history:get'])]
    private string $departureTime = '00:00';

    #[ORM\Column(name: 'SH_EFFECTIVE_FROM')]
    #[Groups(['agency_offer_schedule_history:get'])]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\Column(name: 'SH_EFFECTIVE_TO', nullable: true)]
    #[Groups(['agency_offer_schedule_history:get'])]
    private ?\DateTimeImmutable $effectiveTo = null;

    #[ORM\Column(name: 'SH_CREATED_AT')]
    #[Groups(['agency_offer_schedule_history:get'])]
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

    public function getDepartureTime(): string
    {
        return $this->departureTime;
    }

    public function setDepartureTime(string $departureTime): static
    {
        $this->departureTime = $departureTime;

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
