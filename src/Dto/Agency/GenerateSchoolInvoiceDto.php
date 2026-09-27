<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class GenerateSchoolInvoiceDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-(0[1-9]|1[0-2])$/')]
        public ?string $periodYm = null,
    ) {
    }
}
