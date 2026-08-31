<?php

namespace App\Dto\Agency;

use App\Entity\Agency;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyRentalContractDto
{
    public function __construct(
        public ?string $transport = null,

        public ?string $driver = null,

        #[Assert\Length(max: 120)]
        public ?string $clientName = null,

        #[Assert\Length(max: 20)]
        public ?string $clientPhone = null,

        #[Assert\Length(max: 120)]
        public ?string $clientCompany = null,

        public ?string $startAt = null,

        public ?string $endAt = null,

        #[Assert\Length(max: 160)]
        public ?string $pickupLocation = null,

        #[Assert\Length(max: 160)]
        public ?string $dropoffLocation = null,

        #[Assert\Positive]
        public ?int $dailyRate = null,

        #[Assert\PositiveOrZero]
        public ?int $totalAmount = null,

        #[Assert\PositiveOrZero]
        public ?int $depositAmount = null,

        #[Assert\Currency]
        public ?string $currency = null,

        public ?string $notes = null,
    ) {
    }
}
