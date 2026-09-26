<?php

namespace App\Dto\Traveler;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTravelerPassPurchaseDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $productId = null,

        public bool $payWithWallet = true,
    ) {
    }
}
