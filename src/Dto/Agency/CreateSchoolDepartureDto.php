<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateSchoolDepartureDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $contractId = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $date = null,

        public ?string $transportId = null,

        public ?string $driverId = null,
    ) {
    }
}
