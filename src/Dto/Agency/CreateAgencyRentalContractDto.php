<?php

namespace App\Dto\Agency;

use App\Entity\Agency;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyRentalContractDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $transport = null,

        public ?string $driver = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $clientName = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $clientPhone = null,

        #[Assert\Length(max: 120)]
        public ?string $clientCompany = null,

        #[Assert\NotBlank]
        public ?string $startAt = null,

        #[Assert\NotBlank]
        public ?string $endAt = null,

        #[Assert\Length(max: 160)]
        public ?string $pickupLocation = null,

        #[Assert\Length(max: 160)]
        public ?string $dropoffLocation = null,

        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $dailyRate = null,

        #[Assert\NotNull]
        #[Assert\PositiveOrZero]
        public ?int $totalAmount = null,

        #[Assert\PositiveOrZero]
        public ?int $depositAmount = null,

        #[Assert\Currency]
        public ?string $currency = Agency::DEFAULT_CURRENCY,

        public ?string $notes = null,
    ) {
    }
}
