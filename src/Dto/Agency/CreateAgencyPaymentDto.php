<?php

namespace App\Dto\Agency;

use App\Entity\AgencyPayment;
use Symfony\Component\Validator\Constraints as Assert;

class CreateAgencyPaymentDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $ticket = null,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [AgencyPayment::class, 'getMethodsAsList'])]
        public ?string $method = AgencyPayment::METHOD_CASH,

        public ?string $notes = null,
    ) {
    }
}
