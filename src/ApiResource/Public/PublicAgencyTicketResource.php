<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;

final class PublicAgencyTicketResource
{
    /**
     * @param array{id: string, label: string, origin: string, destination: string, departureTime: string} $offer
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $publicToken,
        public string $ticketId,
        public string $reference,
        public string $status,
        public string $passengerName,
        public string $passengerPhone,
        public string $seatNumber,
        public string $travelDate,
        public int $ticketPrice,
        public int $passPrice,
        public string $currency,
        public bool $hasExistingPass,
        public ?string $qrPayload,
        public array $offer,
        public ?string $pdfUrl = null,
    ) {
    }
}
