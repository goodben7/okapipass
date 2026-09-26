<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class ValidatePromotionDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public ?string $code = null,

        public ?string $offer = null,

        #[Assert\PositiveOrZero]
        public ?int $ticketPrice = null,

        #[Assert\Length(max: 20)]
        public ?string $phone = null,
    ) {
    }
}
