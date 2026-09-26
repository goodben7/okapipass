<?php

namespace App\Domain\Agency;

use App\Entity\AgencyOffer;
use App\Exception\UnprocessableEntityException;

/**
 * Unaccompanied minor validation against offer.minUnaccompaniedAge.
 */
final class AgencyMinorAccompanimentValidator
{
    public function assertAllowed(
        AgencyOffer $offer,
        ?\DateTimeImmutable $passengerDob,
        ?string $escortTicketId,
        ?string $escortName,
        \DateTimeImmutable $travelDate,
    ): void {
        $minAge = $offer->getMinUnaccompaniedAge();
        if (null === $minAge) {
            return;
        }
        if (!$passengerDob instanceof \DateTimeImmutable) {
            return;
        }

        $age = $passengerDob->diff($travelDate)->y;
        if ($age >= $minAge) {
            return;
        }

        $hasEscort = (null !== $escortTicketId && '' !== trim($escortTicketId))
            || (null !== $escortName && '' !== trim($escortName));

        if (!$hasEscort) {
            throw new UnprocessableEntityException(sprintf(
                'Passenger under %d years requires an escort (escortTicketId or escortName).',
                $minAge,
            ));
        }
    }
}
