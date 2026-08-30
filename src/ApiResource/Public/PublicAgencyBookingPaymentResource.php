<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;

final class PublicAgencyBookingPaymentResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $publicToken,
        public string $bookingId,
        public string $paymentId,
        public string $paymentStatus,
        public string $paymentMethod,
        public int $amount,
        public string $currency,
        public ?string $providerTransactionId = null,
        public ?string $cardFormUrl = null,
        public ?string $ticketReference = null,
        public string $bookingStatus = '',
        public string $bookingPaymentStatus = '',
    ) {
    }
}
