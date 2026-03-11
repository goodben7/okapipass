<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserDto
{
    public function __construct(
        
        #[Assert\Email]
        public ?string $email = null,

        #[Assert\Length(max: 15)]
        public ?string $phone = null,

        #[Assert\Length(max: 120)]
        public ?string $displayName = null,

    )
    {
    }
}