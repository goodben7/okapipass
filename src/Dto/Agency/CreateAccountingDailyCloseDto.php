<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateAccountingDailyCloseDto
{
    public function __construct(
        #[Assert\NotBlank]
        /** Y-m-d */
        public ?string $businessDate = null,

        public ?string $notes = null,
    ) {
    }
}
