<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationPreviewDto
{
    public function __construct(
        /** booking | ticket */
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['booking', 'ticket'])]
        public ?string $type = null,

        /** Agency booking or ticket id (not an API Platform resource IRI). */
        #[Assert\NotBlank]
        public ?string $targetId = null,
    ) {
    }
}
