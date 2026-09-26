<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyWorkOrderDto
{
    public function __construct(
        #[Assert\Length(max: 160)]
        public ?string $title = null,

        public ?string $description = null,

        #[Assert\PositiveOrZero]
        public ?int $partsCost = null,

        #[Assert\PositiveOrZero]
        public ?int $laborCost = null,

        public ?bool $immobilize = null,

        #[Assert\Length(max: 120)]
        public ?string $vendorName = null,
    ) {
    }
}
