<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class RequestOtpDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $phone = null,
    ) {
    }
}
