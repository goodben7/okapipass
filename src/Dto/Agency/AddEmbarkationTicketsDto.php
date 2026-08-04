<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class AddEmbarkationTicketsDto
{
    public function __construct(
        /** @var list<string> */
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        public ?array $ticketIds = null,
    ) {
    }
}
