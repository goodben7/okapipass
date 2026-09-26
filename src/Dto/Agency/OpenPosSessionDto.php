<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class OpenPosSessionDto
{
    public function __construct(
        #[Assert\Length(max: 80)]
        public ?string $pointOfSale = null,

        public ?string $notes = null,

        #[Assert\Length(max: 80)]
        public ?string $deviceId = null,

        #[Assert\Length(max: 20)]
        public ?string $pin = null,
    ) {
    }
}
