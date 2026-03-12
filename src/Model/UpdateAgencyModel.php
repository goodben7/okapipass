<?php

namespace App\Model;

use App\Entity\Agency;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateAgencyModel
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $name = null,

        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,

        #[Assert\Length(max: 15)]
        public ?string $phone = null,

        #[Assert\Length(max: 255)]
        public ?string $address = null,

        #[Assert\Choice(callback: [Agency::class, 'getStatusesAsList'])]
        public ?string $status = null,
    ) {
    }
}
