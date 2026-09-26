<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyDepotDto
{
    public function __construct(
        #[Assert\Length(max: 40)]
        public ?string $code = null,

        #[Assert\Length(max: 160)]
        public ?string $label = null,

        public ?bool $active = null,
    ) {
    }
}
