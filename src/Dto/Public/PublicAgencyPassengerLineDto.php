<?php

namespace App\Dto\Public;

use Symfony\Component\Validator\Constraints as Assert;

final class PublicAgencyPassengerLineDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public ?string $seatNumber = null,

        #[Assert\Length(max: 120)]
        public ?string $passengerName = null,

        #[Assert\Length(max: 60)]
        public ?string $passengerId = null,

        #[Assert\Length(max: 20)]
        public ?string $passengerPhone = null,

        #[Assert\Length(max: 40)]
        public ?string $okapiPassRef = null,
    ) {
    }
}
