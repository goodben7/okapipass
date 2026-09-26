<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class ReleaseNoShowsDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $offerId = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,
    ) {
    }
}
