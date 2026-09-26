<?php

namespace App\Dto\Agency;

final class UpdateAgencyParcelDto
{
    public function __construct(
        public ?string $offer = null,
        public ?string $transport = null,
        public ?string $embarkation = null,
        public ?string $senderName = null,
        public ?string $senderPhone = null,
        public ?string $recipientName = null,
        public ?string $recipientPhone = null,
        public ?float $weightKg = null,
        public ?int $fee = null,
        public ?string $currency = null,
        public ?string $travelDate = null,
        public ?string $notes = null,
    ) {
    }
}
