<?php

namespace App\Model;

use App\Entity\Checkpoint;
use App\Entity\GoPass;
use App\Entity\Payment;
use Symfony\Component\Validator\Constraints as Assert;

class NewTicketModel
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $displayName = null,

        #[Assert\Length(max: 15)]
        public ?string $phone = null,

        #[Assert\Length(max: 255)]
        public ?string $identifier = null,

        #[Assert\NotNull]
        public ?GoPass $goPass = null,

        #[Assert\NotNull]
        public ?Checkpoint $departure = null,

        #[Assert\NotNull]
        public ?Checkpoint $arrival = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 30)]
        #[Assert\Choice(callback: [Payment::class, 'getMethodsAsList'])]
        public ?string $method = null,
    ) {
    }
}
