<?php

namespace App\ApiResource\Public;

final class PublicAgencyPassengerLineResource
{
    /**
     * @param array{ticketPrice: int, passPrice: int, total: int, currency: string, hasExistingPass: bool} $quote
     */
    public function __construct(
        public string $bookingId,
        public string $seatNumber,
        public ?string $passengerName,
        public string $passengerId,
        public ?string $passengerPhone,
        public ?string $okapiPassRef,
        public array $quote,
    ) {
    }
}
