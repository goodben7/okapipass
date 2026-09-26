<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class VerifyOtpDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $phone = null,

        #[Assert\NotBlank]
        #[Assert\Length(min: 4, max: 8)]
        public ?string $code = null,
    ) {
    }
}
