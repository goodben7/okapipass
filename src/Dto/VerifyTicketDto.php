<?php

namespace App\Dto;

use App\Entity\Checkpoint;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VerifyTicketDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $uniqueReference = null,

        #[Assert\NotNull]
        public ?Checkpoint $checkpoint = null,

        public ?string $comment = null,
    ) {
    }
}
