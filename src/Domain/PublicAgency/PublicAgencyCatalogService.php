<?php

namespace App\Domain\PublicAgency;

use App\Entity\AgencyOffer;
use App\Exception\UnavailableDataException;
use App\Repository\AgencyOfferRepository;

/**
 * Resolves agency offers visible on the public B2C catalogue.
 */
final class PublicAgencyCatalogService
{
    public function __construct(
        private AgencyOfferRepository $offers,
    ) {
    }

    public function requireOnlineOffer(string $id): AgencyOffer
    {
        $offer = $this->offers->findPublicOnlineById($id);
        if (!$offer instanceof AgencyOffer) {
            throw new UnavailableDataException(sprintf('Offer "%s" not found or not available online.', $id));
        }

        return $offer;
    }
}
