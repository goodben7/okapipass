<?php

namespace App\Dto\Traveler;

use Symfony\Component\Validator\Constraints as Assert;

final class ShareTravelerTicketDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $toPhone = null,
    ) {
    }
}
