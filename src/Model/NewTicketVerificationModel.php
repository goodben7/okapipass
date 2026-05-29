<?php

namespace App\Model;

use App\Entity\Checkpoint;

final readonly class NewTicketVerificationModel
{
    public function __construct(
        public string $uniqueReference,
        public Checkpoint $checkpoint,
        public ?string $comment = null,
    ) {
    }
}
