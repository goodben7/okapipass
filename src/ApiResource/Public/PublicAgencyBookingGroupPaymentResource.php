<?php

namespace App\ApiResource\Public;

use ApiPlatform\Metadata\ApiProperty;

final class PublicAgencyBookingGroupPaymentResource
{
    /**
     * @param list<string> $ticketReferences
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $publicToken,
        public string $groupId,
        public string $groupName,
        public string $paymentId,
        public string $paymentStatus,
        public string $paymentMethod,
        public int $amount,
        public string $currency,
        public int $passengerCount,
        public ?string $providerTransactionId = null,
        public ?string $cardFormUrl = null,
        public array $ticketReferences = [],
        public string $groupStatus = '',
        public string $groupPaymentStatus = '',
    ) {
    }
}
