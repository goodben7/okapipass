<?php

namespace App\Model;

use App\Entity\Profile;
use App\Enum\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class NewUserModel
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

        #[Assert\Length(max: 16)]
        public ?string $holderId = null,

        #[Assert\Choice(callback: [EntityType::class, 'getAll'], message: 'Invalid holder type.')]
        public ?string $holderType = null,

    )
    {
    }
}