<?php

namespace App\Dto\Traveler;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTravelerPreorderDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $offerId = null,

        #[Assert\NotBlank]
        public ?string $travelDate = null,

        #[Assert\Positive]
        public ?int $quantity = 1,

        #[Assert\Length(max: 160)]
        public ?string $passengerName = null,

        public ?string $beneficiaryId = null,
    ) {
    }
}
