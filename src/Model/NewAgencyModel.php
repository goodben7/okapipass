<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class NewAgencyModel
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $name = null,

        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,

        #[Assert\Length(max: 15)]
        public ?string $phone = null,

        #[Assert\Length(max: 255)]
        public ?string $address = null
    ) {
    }
}
