<?php

namespace App\Dto\Agency;

use App\Entity\AgencyEmbarkation;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyEmbarkationStatusDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: [
            AgencyEmbarkation::STATUS_BOARDING,
            AgencyEmbarkation::STATUS_DEPARTED,
            AgencyEmbarkation::STATUS_CLOSED,
        ])]
        public ?string $status = null,
    ) {
    }
}
