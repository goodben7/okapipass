<?php

namespace App\Domain\Agency;

use App\Entity\AgencyOffer;
use App\Entity\AgencyOfferPriceHistory;
use App\Repository\AgencyOfferPriceHistoryRepository;

final class AgencyOfferEffectivePriceResolver
{
    public function __construct(
        private AgencyOfferPriceHistoryRepository $history,
    ) {
    }

    public function resolve(AgencyOffer $offer, ?\DateTimeImmutable $travelDate = null): int
    {
        $at = ($travelDate ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $row = $this->history->findEffectivePrice($offer, $at);
        if ($row instanceof AgencyOfferPriceHistory) {
            return $row->getTicketPrice();
        }

        return (int) $offer->getTicketPrice();
    }
}
