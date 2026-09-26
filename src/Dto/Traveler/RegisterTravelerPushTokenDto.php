<?php

namespace App\Dto\Traveler;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterTravelerPushTokenDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 512)]
        public ?string $deviceToken = null,

        #[Assert\Length(max: 40)]
        public ?string $platform = null,
    ) {
    }
}
