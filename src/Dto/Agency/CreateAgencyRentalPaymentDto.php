<?php

namespace App\Dto\Agency;

use App\Entity\AgencyPayment;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyRentalPaymentDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyPayment::class, 'getMethodsAsList'])]
        public ?string $method = AgencyPayment::METHOD_CASH,

        #[Assert\Positive]
        public ?int $amount = null,

        public ?string $notes = null,
    ) {
    }
}
