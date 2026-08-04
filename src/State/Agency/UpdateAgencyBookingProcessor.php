<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyBookingDto;
use App\Entity\AgencyBooking;
use App\Exception\UnavailableDataException;
use App\Manager\AgencyBookingManager;
use App\Repository\AgencyBookingRepository;
use App\Service\Agency\AgencyContext;

/** @implements ProcessorInterface<UpdateAgencyBookingDto, AgencyBooking> */
final class UpdateAgencyBookingProcessor implements ProcessorInterface
{
    public function __construct(
        private AgencyBookingManager $manager,
        private AgencyBookingRepository $bookings,
        private AgencyContext $agencyContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        \assert($data instanceof UpdateAgencyBookingDto);
        $booking = $this->bookings->find($uriVariables['id'] ?? null);
        if (null === $booking) {
            throw new UnavailableDataException('Booking not found.');
        }
        $this->agencyContext->assertOwns($booking->getAgency());

        return $this->manager->update($booking, $data);
    }
}
