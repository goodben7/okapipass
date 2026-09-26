<?php

namespace App\Dto\Traveler;

use App\Entity\WalletTopup;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateWalletTopupDto
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $amount = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        #[Assert\Choice(callback: [WalletTopup::class, 'getMethodsAsList'])]
        public ?string $method = WalletTopup::METHOD_MOBILE_MONEY,
    ) {
    }
}
