<?php

namespace App\Dto;

use App\Entity\Profile;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDto
{
    public function __construct(
        
        #[Assert\Email]
        public ?string $email = null,

        #[Assert\NotNull]
        #[Assert\NotBlank]
        public ?string $plainPassword = null,

        #[Assert\NotNull]
        #[Assert\NotBlank]
        public ?Profile $profile = null,

        #[Assert\Length(max: 15)]
        public ?string $phone = null,

        #[Assert\Length(max: 120)]
        public ?string $displayName = null,
    )
    {
    }
}